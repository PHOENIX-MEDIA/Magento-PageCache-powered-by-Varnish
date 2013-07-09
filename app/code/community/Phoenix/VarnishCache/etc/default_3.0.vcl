# This is a basic VCL configuration file for PageCache powered by Varnish for Magento module.

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

sub vcl_recv {
    if (req.restarts == 0) {
        if (req.http.x-forwarded-for) {
            set req.http.X-Forwarded-For =
            req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    if (req.request != "GET" &&
        req.request != "HEAD" &&
        req.request != "PUT" &&
        req.request != "POST" &&
        req.request != "TRACE" &&
        req.request != "OPTIONS" &&
        req.request != "DELETE" &&
        req.request != "PURGE") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (pipe);
    }

    # purge request
    if (req.request == "PURGE") {
        if (!client.ip ~ purge) {
            error 405 "Not allowed.";
        }
        ban("obj.http.X-Purge-Host ~ " + req.http.X-Purge-Host + " && obj.http.X-Purge-URL ~ " + req.http.X-Purge-Regex + " && obj.http.Content-Type ~ " + req.http.X-Purge-Content-Type);
        error 200 "Purged.";
    }

    # switch to admin backend configuration
    if (req.http.cookie ~ "adminhtml=") {
        set req.backend = admin;
    }

    # we only deal with GET and HEAD by default
    if (req.request != "GET" && req.request != "HEAD") {
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
    set req.url = regsuball(req.url,"\?gclid=[^&]+$",""); # strips when QS = "?gclid=AAA"
    set req.url = regsuball(req.url,"\?gclid=[^&]+&","?"); # strips when QS = "?gclid=AAA&foo=bar"
    set req.url = regsuball(req.url,"&gclid=[^&]+",""); # strips when QS = "?foo=bar&gclid=AAA" or QS = "?foo=bar&gclid=AAA&bar=baz"

    return (lookup);
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
#
# sub vcl_pass {
#     return (pass);
# }
#
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
        remove req.http.pageCacheEnv;
    }

    if (!(req.url ~ "^/(media|js|skin)/.*\.(png|jpg|jpeg|gif|css|js|swf|ico)$")) {
        call design_exception;
    }
    return (hash);
}
#
# sub vcl_hit {
#     return (deliver);
# }
#
# sub vcl_miss {
#     return (fetch);
# }

sub vcl_fetch {
    if (beresp.status == 500) {
       set beresp.saintmode = 10s;
       return (restart);
    }
    set beresp.grace = 5m;

    # enable ESI feature if needed
    if (beresp.http.X-Cache-DoEsi == "1") {
        set beresp.do_esi = true;
    }

    # add ban-lurker tags to object
    set beresp.http.X-Purge-URL = req.url;
    set beresp.http.X-Purge-Host = req.http.host;

    if (beresp.status == 200 || beresp.status == 301 || beresp.status == 404) {
        if (beresp.http.Content-Type ~ "text/html" || beresp.http.Content-Type ~ "text/xml") {
            if ((beresp.http.Set-Cookie ~ "NO_CACHE=") || (beresp.ttl < 1s)) {
                set beresp.ttl = 0s;
                return (hit_for_pass);
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

    return (hit_for_pass);
}

sub vcl_deliver {
    # debug info
    if (resp.http.X-Cache-Debug) {
        if (obj.hits > 0) {
            set resp.http.X-Cache = "HIT";
            set resp.http.X-Cache-Hits = obj.hits;
        } else {
           set resp.http.X-Cache = "MISS";
        }
        set resp.http.X-Cache-Expires = resp.http.Expires;
    } else {
        # remove Varnish/proxy header
        remove resp.http.X-Varnish;
        remove resp.http.Via;
        remove resp.http.Age;
        remove resp.http.X-Purge-URL;
        remove resp.http.X-Purge-Host;
    }

    if (resp.http.magicmarker) {
        # Remove the magic marker
        unset resp.http.magicmarker;

        set resp.http.Cache-Control = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
        set resp.http.Pragma = "no-cache";
        set resp.http.Expires = "Mon, 31 Mar 2008 10:00:00 GMT";
        set resp.http.Age = "0";
    }
}

# sub vcl_error {
#     set obj.http.Content-Type = "text/html; charset=utf-8";
#     set obj.http.Retry-After = "5";
#     synthetic {"
# <?xml version="1.0" encoding="utf-8"?>
# <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
#  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
# <html>
#   <head>
#     <title>"} + obj.status + " " + obj.response + {"</title>
#   </head>
#   <body>
#     <h1>Error "} + obj.status + " " + obj.response + {"</h1>
#     <p>"} + obj.response + {"</p>
#     <h3>Guru Meditation:</h3>
#     <p>XID: "} + req.xid + {"</p>
#     <hr>
#     <p>Varnish cache server</p>
#   </body>
# </html>
# "};
#     return (deliver);
# }
#
# sub vcl_init {
#   return (ok);
# }
#
# sub vcl_fini {
#   return (ok);
# }

sub design_exception {
}
