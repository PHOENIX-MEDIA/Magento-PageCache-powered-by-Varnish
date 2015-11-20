# This is a basic VCL configuration file for PageCache powered by Varnish for Magento module.

vcl 4.0;
# include variable handling methods
include "vars.vcl";

# default backend definition.  Set this to point to your content server.
backend default {
  .host = "127.0.0.1";
  .port = "8080";
}

# admin backend with longer timeout values. Set this to the same IP & port as your default server.
backend admin {
  .host = "127.0.0.1";
  .port = "8080";
  .first_byte_timeout = 18000s;
  .between_bytes_timeout = 18000s;
}

# add your Magento server IP to allow purges from the backend
acl purge {
  "localhost";
  "127.0.0.1";
}

import std;

sub vcl_init {
    C{
	    /* set random salt */
        srand(time(NULL));

	    /* init var storage */
	    init_function(NULL, NULL);
    }C
}

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.x-forwarded-for) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE" &&
        req.method != "PURGE") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (pipe);
    }

    # purge request
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        ban("obj.http.X-Purge-Host ~ " + req.http.X-Purge-Host + " && obj.http.X-Purge-URL ~ " + req.http.X-Purge-Regex + " && obj.http.Content-Type ~ " + req.http.X-Purge-Content-Type);
        return (synth(200, "Purged."));
    }

    # switch to admin backend configuration
    if (req.http.cookie ~ "adminhtml=") {
        set req.backend_hint = admin;
    }

    # we only deal with GET and HEAD by default
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # normalize url in case of leading HTTP scheme and domain
    set req.url = regsub(req.url, "^http[s]?://[^/]+", "");

    # collect all cookies
    std.collect(req.http.Cookie);

    # static files are always cacheable. remove SSL flag and cookie
    if (req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$") {
        unset req.http.Https;
        unset req.http.Cookie;
    }

    # check if we have a formkey cookie
    if (req.http.Cookie ~ "PAGECACHE_FORMKEY") {
    	set req.http.x-var-input = regsub(req.http.cookie, ".*PAGECACHE_FORMKEY=([^;]*)(;*.*)?", "\1");
	    call var_set;
    } else {
        # create formkey once
        if (req.esi_level == 0) {
            C{
                generate_formkey(ctx, 16);
            }C
            set req.http.x-var-input = req.http.X-Pagecache-Formkey;
            call var_set;
        }
    }
    # cleanup variables
    unset req.http.x-var-input;
    unset req.http.X-Pagecache-Formkey;

    # formkey lookup
    if (req.url ~ "/varnishcache/getformkey/") {
	    call var_get;
        return (synth(760, req.http.x-var-output));
    }

    # not cacheable by default
    if (req.http.Authorization || req.http.Https) {
        return (pass);
    }

    # do not cache any page from index files
    if (req.url ~ "^/(index)") {
        return (pass);
    }

    # as soon as we have a NO_CACHE cookie pass request
    if (req.http.cookie ~ "NO_CACHE=") {
        return (pass);
    }

    # remove Google gclid parameters
    set req.url = regsuball(req.url, "\?gclid=[^&]+$", "");  # strips when QS = "?gclid=AAA"
    set req.url = regsuball(req.url, "\?gclid=[^&]+&", "?"); # strips when QS = "?gclid=AAA&foo=bar"
    set req.url = regsuball(req.url, "&gclid=[^&]+",   "");  # strips when QS = "?foo=bar&gclid=AAA" or QS = "?foo=bar&gclid=AAA&bar=baz"

    return (hash);
}

# sub vcl_pipe {
#     # Note that only the first request to the backend will have
#     # X-Forwarded-For set.  If you use X-Forwarded-For and want to
#     # have it set for all requests, make sure to have:
#     # set bereq.http.connection = "close";
#     # here.  It is not set by default as it might break some broken web
#     # applications, like IIS with NTLM authentication.
#     return (pipe);
# }

# sub vcl_pass {
#     return (pass);
# }

sub vcl_hash {
    hash_data(req.url);
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    if (req.http.cookie ~ "PAGECACHE_ENV=") {
        set req.http.pageCacheEnv = regsub(
            req.http.cookie,
            "(.*)PAGECACHE_ENV=([^;]*)(.*)",
            "\2"
        );
        hash_data(req.http.pageCacheEnv);
        unset req.http.pageCacheEnv;
    }

    if (!(req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$")) {
        call design_exception;
    }
    return (lookup);
}

# sub vcl_hit {
#     return (deliver);
# }

# sub vcl_miss {
#     return (fetch);
# }

sub vcl_backend_response {
    if (beresp.status >= 500) {
       if (beresp.http.Content-Type ~ "text/xml") {
           return (deliver);
       }
       return (retry);
    }
    set beresp.grace = 5m;

    # enable ESI feature if needed
    if (beresp.http.X-Cache-DoEsi == "1") {
        set beresp.do_esi = true;
    }

    # add ban-lurker tags to object
    set beresp.http.X-Purge-URL  = bereq.url;
    set beresp.http.X-Purge-Host = bereq.http.host;

    if (beresp.status == 200 || beresp.status == 301 || beresp.status == 404) {
        if (beresp.http.Content-Type ~ "text/html" || beresp.http.Content-Type ~ "text/xml") {
            if ((beresp.http.Set-Cookie ~ "NO_CACHE=") || (beresp.ttl < 1s)) {
                set beresp.ttl = 0s;
                set beresp.uncacheable = true;
                return (deliver);
            }

            # marker for vcl_deliver to reset Age:
            set beresp.http.magicmarker = "1";

            # Don't cache cookies
            unset beresp.http.set-cookie;
        } else {
            # set default TTL value for static content
            set beresp.ttl = 4h;
        }
        return (deliver);
    }

    set beresp.uncacheable = true;
    return (deliver);
}

sub vcl_deliver {
    # debug info
    if (resp.http.X-Cache-Debug) {
        if (obj.hits > 0) {
            set resp.http.X-Cache      = "HIT";
            set resp.http.X-Cache-Hits = obj.hits;
        } else {
            set resp.http.X-Cache      = "MISS";
        }
        set resp.http.X-Cache-Expires  = resp.http.Expires;
    } else {
        # remove Varnish/proxy header
        unset resp.http.X-Varnish;
        unset resp.http.Via;
        unset resp.http.Age;
        unset resp.http.X-Purge-URL;
        unset resp.http.X-Purge-Host;
    }

    if (resp.http.magicmarker) {
        # Remove the magic marker
        unset resp.http.magicmarker;

        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
        set resp.http.Pragma        = "no-cache";
        set resp.http.Expires       = "Mon, 31 Mar 2008 10:00:00 GMT";
        set resp.http.Age           = "0";
    }
}

sub vcl_backend_error {
    # workaround for possible security issue
    #if (beresp.url ~ "^\s") {
    #    set beresp.status = 400;
    #    set beresp.reason = "Malformed request";
    #    synthetic("");
    #    return(deliver);
    #}

    # formkey request
    if (beresp.status == 760) {
        set beresp.status = 200;
	    synthetic(beresp.reason);
        return(deliver);
    }

    # error 200
    if (beresp.status == 200) {
        return (deliver);
    }

     set beresp.http.Content-Type = "text/html; charset=utf-8";
     set beresp.http.Retry-After = "5";
     synthetic({"
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <title>"} + beresp.status + " " + beresp.reason + {"</title>
    </head>
    <body>
        <h1>Error "} + beresp.status + " " +beresp.reason + {"</h1>
        <p>"} + beresp.reason + {"</p>
        <h3>Guru Meditation:</h3>
        <p>XID: "} + bereq.xid + {"</p>
        <hr>
        <p>Varnish cache server</p>
    </body>
</html>
"});
     return (deliver);
}


# sub vcl_fini {
#   return (ok);
# }

sub design_exception {
}

C{
    #include <string.h>
    #include <stdio.h>
    #include <stdlib.h>
    #include <time.h>

    /**
     * create a random alphanumeric string and store it in
     * the request header as X-Pagecache-Formkey
     */
    char *generate_formkey(const struct vrt_ctx *ctx, int maxLength) {
        char *validChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        int validCharsLength = strlen(validChars);
        char *result = (char *) malloc(maxLength + 1);

        // generate string
        int i;
        for (i = 0; i < maxLength; ++i) {
            int charPosition = rand() % validCharsLength;
            result[i] = validChars[charPosition];
        }
        result[maxLength] = '\0';

        // set req.X-Country-Code header
        VRT_SetHdr(ctx, HDR_REQ, "\024X-Pagecache-Formkey:", result, vrt_magic_string_end);

        return 0;
    }
}C
