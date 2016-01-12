<!-- *******************************************************************************
* "PageCache powered by Varnish" – Documentation
* Copyright 2011-2013 by PHOENIX MEDIA GmbH (http://www.phoenix-media.eu)
* "VARNISH" is a registered trademark of Varnish Software AB #http://www.varnish-software.com/)
* "Magento" is a registered trademark of X.commerce Inc. #http://www.magento.com)
****************************************************************************** -->

# Table of contents
1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)  
    3.1 [Varnish 4.1](#installation)  
    3.2 [Libraries needed](#installation)  
4. [Configuration](#configuration)  
    4.1 [PageCache module](#configuration)  
    4.2 [Varnish Cache (VCL)](#configuration)  
    4.3 [ESI](#configuration)  
5. [Cache cleaning, PURGE requests](#cleancache)
6. [VCL Design Exceptions](#designexceptions)
7. [Troubleshooting](#troubleshooting)  
   7.1 [Known issues](#troubleshooting)  
   7.2 [Prevent caching for custom modules](#troubleshooting)  
   7.3 [Prevent caching for HTML/PHP files outside Magento](#troubleshooting)  
   7.4 [Debugging requests](#troubleshooting)  
   7.5 [Vary HTTP header for User-Agent](#troubleshooting)  
8.  [Changelog](#changelog)


# 1. <a name="introduction"></a>Introduction

Thank you for using **"PageCache powered by Varnish"** (PageCache) module.
This package contains everything you need to connect Varnish Cache with your Magento Commerce shop and to get the most out of Varnish’s powerful caching capabilities for a blazing fast eCommerce site.

The PageCache module has been architectural certified by Varnish Software to ensure highest quality and reliability for Magento stores.
PHOENIX MEDIA is a Varnish Integration Partner and Magento Gold Partner in Germany and Austria and is maintaining this module as well as providing professional services for Varnish and Magento Commerce.

The PageCache module consists of two main components:
- The Magento module and
- the bundled Varnish Cache configuration file (VCL).

The PageCache module basically sets the correct Cache-Control headers according to the configuration and the visitor session and provides an interface for cleaning Varnish’s cache.
The second component, the VCL, configures Varnish to process the client requests and Magento’s HTML response according to the Cache-Control headers the PageCache module adds to every response.

Beside shop pages Varnish will also cache static content that is served by the web server like product images, CSS or JavaScript files or even flash or PDF resources.
While you can also clean Varnish’s static content cache from the Magento Cache Management page the PageCache configuration won’t affect any of the static file’s HTTP headers as they are directly served by the web server.


# 2. <a name="prerequisites"></a>Prerequisites
Before installing the PageCache module you should setup a test hosting environment as you will need to change the web server’s port configuration and put Varnish in front which will certainly take a while for configuring and testing.
If you directly rollout this solution to your production server you will certainly have down times which should be prevented to not annoy your customers.

Ensure that your Magento Commerce shop is running without any problems in your environment as debugging Magento issues with a proxy like Varnish in front might be difficult.

PageCache supports Magento Community Edition from version 1.5.1 and Magento Professional/Enterprise Edition from version 1.9.0 on.

Furthermore you should have installed Varnish >= 3.0 on your Linux server.
To install Varnish Cache refer to the excellent documentation at http://www.varnish-cache.org/docs/ where you will also find lots of information about VCL (Varnish Configuration Language), the heart of Varnish.
If you need professional service for setup your server please contact Varnish Software for commercial support for the Varnish Cache software. 

Check out their website or send them a mail at sales@varnish-software.com.


# 3. <a name="installation"></a>Installation
The installation of the Magento module is pretty easy:

1. Copy the contents of the app directory in the archive to the app directory of your Magento instance.
2. Go to the Magento backend and open the Cache Management (**System &rarr; Cache Management**) and refresh the configuration and layout cache.

If any critical issue occurs you can’t easily solve, go to `app/etc/modules`, open `Phoenix_VarnishCache.xml` and set **"false"** in the **"active"** tag to deactivate the PageCache module.
If necessary clear Magento’s cache again.

As Varnish is already installed on your server you should just make a backup of your default.vcl file which is shipped with Varnish.
It should be located at `/etc/varnish/default.vcl`.
Copy the VCL file bundled with the PageCache module for your version of Varnish (located at `app/code/local/Phoenix/VarnishCache/etc/default*.vcl`) to your Varnish configuration directory (`/etc/varnish/`).
If you subscribed to the Enterprise Edition of the module you find and improved VCL file with ESI support (see section 4.4) here:  `app/code/local/Phoenix/VarnishCacheEnterprise/etc/esi_3.0.vcl`.

In case you use EE >= 1.13 or CE >= 1.8 and you want to make use of frontend form keys you have to enable ESI within varnish.

We also recommend you check Varnish's startup parameters.
Depending on your OS the location of your startup settings might differ.

For RHEL/CentOS e.g. you find it under `/etc/sysconfig/varnish`.

To make ESI run smoothly add this startup parameters:
```
-p esi_syntax=0x03
```
to make varnish not yell at you cause of "no valid XML"
```
-p shm_reclen=4096
```
to maximize the length for GET requests - 4K should be fair enough.
If this is too short - varnish will truncate your request.

Do not restart the Varnish service until you have checked the new VCL file to adjust your hosting specific values (at least backends and purge ACLs).

Proceed with the configuration.

## 3.1 Varnish 4.1
Since version 4.3.0 this module supports Varnish 4.1
(4.0.x is not supported due to a bug where ESI-included synthetic response is delivered uncompressed within a gzipped response, see https://www.varnish-cache.org/trac/ticket/1688)

To use varnish 4 you have to install version 4.1 and use the corresponding vcl file included within this module.

To make ESI work with varnish 4 add the following to your startup parameters
```
-p feature=+esi_disable_xml_check,+esi_ignore_other_elements \
```
The maximum length for GET requests has also changed and allows a maximum value of 4084
```
-p vsl_reclen=4084 \
```
Inline C has to be allowed explicit with
```
-p vcc_allow_inline_c=on \
```
## 3.2 Libraries needed
As of version 4.3.0 the vcl uses OpenSSLs md5 hashing method to generate formkeys, so you have to make sure that **openssl-devel** package is installed on the server running Varnish.

To make Varnish use the OpenSSL library add the following parameter to the options when starting varnishd
```
-p 'cc_command=exec cc -fpic -shared -Wl,-x -o %o %s -lcrypto -lssl'
```
# 4. <a name="configuration"></a>Configuration
This section handles the different configuration option for the module as well as settings that have to be done on the server side.

## 4.1 PageCache module
In your Magento backend go to **System &rarr; Configuration &rarr; "PageCache powered by Varnish Enterprise"** and open **"General Settings"** tab.

In the following section the configuration options for the PageCache powered by Varnish module are explained. Most of them can be changed on website and store view level which allows fine granulated configurations for different store frontends.

*Note that if you change a value here Varnish will not reflect it until you purge its HTML objects or the TTL of the cached objects expires.*

### Enable cache module
This enables the basic functionality like setting HTTP headers and allows cache cleaning on the Magento Cache Management page.
This option should be set to **"Yes"** globally even if you like to deactivate the module for certain websites or store views.
Otherwise the cleaning options on the Cache Management page won’t be available.

### Varnish servers
Add your Varnish server(s) domains or IPs separated by semicolon.

### Server port
The port your Varnish servers listens on.

This port is used for all servers in your server list.

### Admin Server port
The port your Varnish servers telnet listens to.

This port is used for all servers in your server list.

### Admin server secret
Secret string for CLI authentication.

This file is used for all servers in your server list.

### Disable caching
This option allows you to deactivate caching of every Magento frontend page in Varnish.
This is useful for development or tests by passing all requests through Varnish without caching them.
If you have a staging website within your Magento Enterprise instance make sure this option is set to "Yes" for this website.

*Technically Varnish will still be active but every request gets a TTL of 0.*

### Disable caching for routes
Certain controllers or actions within Magento must not be cached by Varnish as their response surely contains custom data or it is necessary to process a request in database (API calls, payment callbacks).
Although Varnish passes all POST requests (which most often are used to submit forms with custom information etc.) you can also define the controllers and actions that should have the "no-cache" flag in their HTTP response header.

*Note: The function relies on Mage_Core_Controller_Varien_Action::getFullActionName().*

### Disable caching vars
Some GET variables in the frontend will not only change a single page but will be stored in the visitor session and change the output of all following request that might not have this GET parameter.
For example a store view, language or currency switch will only pass the parameter once to Magento to change the output and all following requests will not contain this parameter in the URL as the information is taken from the visitor’s session.

As Varnish is not session aware it can handle only one content per domain + URL combination.
To prevent any conflicts with this behavior the PageCache module will set a **NO_CACHE** cookie to the response to pass all following request by this client through Varnish and let Magento handle the request directly.

### Default cache TTL
Varnish delivers cached objects without requesting the web server or Magento again for a certain period of time defined in the TTL (time to live) value.
You can adjust the TTL for your shop pages on store view level which allows you to have different TTLs for your frontend pages.
*Note that this field only allows numeric values in seconds. It doesn’t support the same notation that can be used in the VCL. "2h" (2 hours) have to be entered as "7200" seconds.*

For static contents Varnish uses the default TTL value defined in the vcl_fetch section of the VCL (Default: set beresp.ttl = 4h).

### Cache TTL for routes
This options allows you to adjust Varnish cache TTL on a per magento controllers/actions basis.
To add a new TTL value for route
    1. click **"Add route"** button
    2. input route (e.g. "cms", "catalog_product_view");
    3. input TTL for route in seconds (e.g. "7200").

**"Default Cache TTL"** value is used when no TTL for a given route is defined.

### Export VCL File
When clicked this button, PageCache module reads VCL loaded to RAM, updates it according to Magento Design Exceptions configurations and serves VCL file for download.
Varnish servers should be restarted using this file to changes take place.

### Purge category
This option binds automatic purge of category (Varnish) cache with its update event.
If you always want up-to-date category information on front-end set the option value to "Yes" and category cache will be invalidated each time a category update occurs.

### Purge product
This option binds purge of product (Varnish) cache with product and product's stock update.
If set to "Yes" product pages cache is invalidated each time product update or product's stock update occurs.
Additionally, if "Purge Category" option is set to "Yes" this triggers product's categories cache purge on product/product stock update.

This option is useful to keep product pages and categories up-to-date when product becomes out of stock (i.e. when the last item purchased by a customer).

### Purge CMS page
This option binds automatic purge of CMS page (Varnish) cache with its update event.
If set to "Yes" CMS page cache is invalidated each time CMS page update event occurs (i.e. CMS page content update via Magento admin).

### Debug
The PageCache module adds several HTTP headers to let Varnish know what to do with the Magento response. Also Varnish adds several tags in the HTTP headers to pass information to the client to allow debugging of requests directly in the browser.
However this information should be removed in production environments which can easily be done by setting this value to "No".

Beside the HTTP headers the PageCache module can also log purge requests to /var/log/varnish_cache.log if the developer log (**System &rarr; Configuration &rarr; Developer**) is enabled.
The log file will allow you to see which PURGE requests have been sent to Varnish.

## 4.2 Varnish Cache (VCL)
PageCache ships with a ready-to-go VCL file that let Magento and Varnish play nicely together.
Although the VCL should be sufficient to start with Magento and Varnish right away you can of course adjust it to your needs if necessary.
Please see VCL documentation at http://www.varnish-cache.org/docs/3.0/reference/vcl.html.

In the "etc" subdirectory you can find different vcl files:
- default_3.0.vcl
- default_4.1.vcl

You have to copy the needed vcl files to your varnish config directory.

When putting Varnish in front of your web server (backend) you will have to change the web server’s port which is normally port 80.
We recommend to change its’ listen port to 8080 which is already configured in the VCL.
If your Varnish server doesn’t run on the same server as your web server (backend) you need to adjust the default backend at the beginning of the VCL file.
Also you will have to adjust the purge ACL below to allow the purge requests which are triggered from the Magento backend which have the IP of your web server (backend).

We also recommend adjusting the **vcl_error** section which will be echoed if the backend (Magento) is not available.

As special feature Varnish’s saint mode has been enabled in the vcl_fetch section by default, which allows Varnish to deliver content even if an object is expired when your backend is unreachable to refresh it.
With this feature your uptime and availability will be increased for better customer experience.

Beside the VCL don't forget to check Varnish's startup parameters.
They allow fine tuning of timeouts, cache size, location of the VCL file and much more.
Checkout Varnish Cache documentation for details.

## 4.3 ESI
Edge Side Includes (ESI) is implemented in Varnish as a subset of the W3C definition (http://www.w3.org/TR/esi-lang) and supports `esi:include` and `esi:remove` only.

With ESI enabled your Magento installation will become even faster than running only Varnish alone.
ESI is used to cache recurring snippets (aka blocks in Magento) and reuse them in different pages.

_Please note that the Page Cache shipped with Magento Enterprise Edition is not compatible with ESI and therefore has to be turned of (**System &rarr; Cache Management**) as it is based on the same application logic (replacing original blocks with placeholders and compile the actual page content on the fly)._

### Form Key Handling
As with version CE 1.8 and EE 1.13 Magento introduced form keys in the frontend.

Form keys are generated by varnish and therefor ESI is always enabled in the vcl files provided with this project.


# 5. <a name="cleancache"></a>Cache cleaning, PURGE requests
Varnish caches objects for a certain period of time according to their TTL.
After that the object will not be requested from the web server or Magento again.
Until the TTL expires Varnish will deliver the cached object no matter what will change within Magento or the webserver’s file system.
To force Varnish to cleanup its’ cache and to retrieve the information again from the backend you can trigger a purge/ban requests right from Magento.

In the Magento backend go to System&rarr;Cache Management.
If you have enabled the PageCache module in the configuration you will see a new button **"Clean Varnish Cache"** in the **"Additional Cache Management"** section.
You can purge all objects in Varnish Cache by just clicking **"Clean Varnish Cache"** or define which store view and/or content type should be purged.
For the store views PageCache will look up the configured domains in **System &rarr; Configuration &rarr; Web** and pass the domain(s) of the selected store view as an argument of the purge request to Varnish.
This will allow you for example to remove the CSS files of a certain store view in Varnish if they have been modified without the need to invalidate any other object which will save a lot of resources on high frequented stores.

It is also possible to purge a single url (e.g. page) using **"Quick Purge"**.
Enter desired URL in input field next to **"Quick Purge"** button and press it.
If URL is valid you'll see a success message for purged page.

Beside these direct purge requests PageCache has observers for "Flush Magento Cache" and "Flush Cache Storage" to purge all objects in Varnish together with the Magento cache refresh.
It also has observers for "Flush Catalog Images Cache" and "Flush JavaScript/CSS Cache" to clean objects that match the appropriate URL path in Varnish.
All HTML objects will be purged too as the product image and JavaScript/CSS paths will change when Magento generated them again so the cached HTML objects might contain wrong paths if not refreshed.
You can also enable automatic purging of CMS pages, categories and products when they are saved (see configuration).
If you don’t want these observers to take automatic action comment them out in the config.xml of the PageCache module.


# 6. <a name="designexceptions"></a>VCL Design Exceptions
By default Varnish does not take into account User-Agent string of a request when building its cache object.
Magento Design Exceptions use regular expressions to match different design configurations to User-Agent strings.
In order to make Design Exceptions work with Varnish you will have to renewVarnish VCL each time Design Exceptions are updated.
Here's what you have to do:
- In your Magento backend go to **System &rarr; Configuration &rarr; "PageCache powered by Varnish Enterprise"** and open **"General Settings"** tab.
- Press **"Export VCL"** button.
- Your browser should start file download your server VCL updated with design
exceptions subroutine.
- Restart your varnish servers using downloaded VCL.

You can run `man varnishd` in command line for description of varnishd options.
Also see documentation explaining how to start varnish for versions 3.0 respectively: https://www.varnish-cache.org/docs/3.0/tutorial/starting_varnish.html


# 7. <a name="troubleshooting"></a>Troubleshooting

## 7.1 Known issues
- "Use SID in Frontend" in **System Configuration &rarr; Web &rarr; Session Validation Settings** must not be set to "yes" otherwise a GET parameter ___SID will be added which disables caching at all.
- "Redirect to CMS-page if Cookies are Disabled" in **System Configuration &rarr; Web &rarr; Browser Capabilities Detection** must be turned off as visitors served by Varnish won't get a cookie until they put something in the cart of login.
- Logging and statistics will be fragmentary (Varnish won't pass cached requests to the webserver or Magento).
Instead make use of a JavaScript based statistics like Google Analytics or contact Varnish Software who offers additional tools for that as part of their subscription services.
- Running FPC (Magento Enterprise Full Page Cache) along with ESI will result in ESI not displaying any cached blocks.
To use Varnish ESI with Magento Enterprise you have to disable FPC completely.

## 7.2 Prevent caching for custom modules
In your Magento installation you will surely have custom modules whose HTML output shouldn’t be cached.
Therefore you have two options:
- Either add their controllers to the "Disable caching for routes" configuration to prevent caching of their output.
- Or, if your module changes the visitor session for all following request, dispatch an event in your Magento module and add an observer to let PageCache set a NO_CACHE cookie (compare config.xml):

```
<catalog_product_compare_add_product>
    <observers>
        <varnishcache>
            <class>varnishcache/observer</class>
            <method>disablePageCachingPermanent</method>
        </varnishcache>
    </observers>
</catalog_product_compare_add_product>
```

## 7.3 Prevent caching for HTML/PHP files outside Magento
Varnish as a proxy respects caching information from the backend server like `"Cache-Control: max-age=600"` or `"Expires: Thu, 19 Nov 2021 08:52:00 GMT"`.
PageCache uses "Expires" to tell Varnish whether a Magento page is cacheable and how long.

If you have mod_expires installed in your Apache and the Magento default setting in your .htaccess `'ExpiresDefault "access plus 1 year"'` this will allow Varnish
to cache every object outside of Magento (e.g. files in js, media or skin folder) for one year.
However this also affects HTML or PHP files if they don't set their own "Cache-Control" or "Expires" header.
If you don't want HTML contents which don't explicitly allow caching to be cached by Varnish, add this line to the mod_expires section of your .htaccess file:
```
    ExpiresByType text/html A0
```
This will set the expiry time of the object equal to the delivery time which will not allow Varnish to cache the object.

*Note that if a "Expires" header is already set in the HTTP response header mod_expires will respect it and pass this header without changes.*

## 7.4 Debugging requests
If Varnish does not behave like you expect there are some great tools that will help you to analyze what’s going on.
First you should activate the debug mode in the PageCache module and purge the HTML objects in **System &rarr; Cache Management** to pass the full HTTP headers to your
browser.

A really great help for debugging requests is the "varnishlog" command that is part of the Varnish distribution.

You can call it on the shell to show only HTML requests:
```
	varnishlog -c -o TxHeader "Content-Type: text/html" (Varnish 2.1)
```
Or of a certain URL:
```
	varnishlog -m RxURL:"^/blog" (Varnish 3.0)
```
You can also filter the output for a certain client IP:
```
	varnishlog -c -o ReqStart 123.456.78.9
```
If you still can’t solve the issues please contact support@phoenix-media.eu to request professional services.

## 7.5 Vary HTTP header for User-Agent
Some administrators have this line in Magento's .htaccess file:
```
    # Make sure proxies don't deliver the wrong content
    Header append Vary User-Agent env=!dont-vary
```
However this forces Varnish Cache to have one cache element per user agent string for each URL which makes caching almost useless.
If you have the feeling that in one browser your cache is hot while in a different browser the Varnish has no cache hits check your backend response with varnishlog and make sure the Vary header only looks like this:
```
    Vary: Accept-Encoding
```



# 8. Changelog
### 4.4.0
- added header check for varnish to signal esi capability
- added vcl for Varnish 4.1

### 4.3.0
- new formkey generation based on request data
- esi data lookup now uses regex

### 4.2.8
- improved environment hashing

### 4.2.7
- fixed geoip handling fallback

### 4.2.6
- removed sid from redirect url in geo ip

### 4.2.5
- added country code lookup
- country mapping is now case insensitive

### 4.2.4
- changed block names for geoip esi tags

### 4.2.3
- added option to disable ESI over HTTPS (#16)

### 4.2.2
- fixed issue #6 by setting DoEsi header when form_keys are replaced to enable ESI processing in VCL
- fixed error in translation (#12)

### 4.2.1
- fixed issue post requests and form keys

### 4.2.0
- added support for frontend form keys, introduced in EE 1.13 and CE 1.8

### 4.1.1
- fixed issue with esi tags being rendered with POST requests

### 4.1.0
- added GeoIP handling

### 4.0.7
- added ESI block for cookie notices

### 4.0.6
- fixed minor issue with vcl

### 4.0.5
- fixed issue with caching of store switching requests

### 4.0.4
- fixed compatibility issue with older magento versions

### 4.0.3
- fixed caching issues with non default stores

### 4.0.2
- fixed issues with cms pages and widgets

### 4.0.1
- fixed issues with catalog search results

### 4.0.0
- added store and currency switch support
- added advanced message handling
- added Varnish ESI support for basic blocks (sidebar/mini cart, top links, welcome message, last viewed and compared products)
- raised Magento version requirements to CE 1.5.1 and EE 1.9
- dropped support for Varnish 2.x
- moved configuration in separate tab
- small bugfixes

### 3.1.1
- Fixed some issues with register_shutdown_function() functionality (introduced in 3.1.0)

### 3.1.0
- Added "Quick Purge" to clean Varnish Cache for a certain URL pattern
- Show VCL snippet for design exception after saving
- Added separate admin backend in VCL with longer timeout values
- Normalize URL in case of leading HTTP scheme and domain in VCL
- Fixed issues where redirects are cached by mistake

### 3.0.1
- Fixed packaging issue with locales

### 3.0.0
- Changed license to OSL for Community Edition
- Instantly purge cache items of CMS pages, categories and products on save
- Configure different TTLs per route/controller
- Use "Cache-Control:s-maxage=x" instead of propriatary headers to control Varnish
- Improved Magento EE compatibility
- Allow frontend Varnish caching while beeing logged in the backend
- Added French translation (thanks to Rubén Romero, Varnish Software)
- Added design exceptions (beta)

### 1.1.0
- Full support for Varnish 3.0
- Replaced no-cache and X-Cache-Ttl header with standard "Expires" header
- Removed C-code in VCLs and splitted default.vcl for 2.1 and 3.0
- Improved hit rate and compatibility for different environments

### 1.0.0
- Initial release
