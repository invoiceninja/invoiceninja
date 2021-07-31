'use strict';
const MANIFEST = 'flutter-app-manifest';
const TEMP = 'flutter-temp-cache';
const CACHE_NAME = 'flutter-app-cache';
const RESOURCES = {
  "assets/assets/images/payment_types/maestro.png": "e533b92bfb50339fdbfa79e3dfe81f08",
"assets/assets/images/payment_types/other.png": "d936e11fa3884b8c9f1bd5c914be8629",
"assets/assets/images/payment_types/amex.png": "c49a4247984b3732a4af50a3390aa978",
"assets/assets/images/payment_types/visa.png": "3ddc4a4d25c946e8ad7e6998f30fd4e3",
"assets/assets/images/payment_types/switch.png": "4fa11c45327f5fdc20205821b2cfd9cc",
"assets/assets/images/payment_types/solo.png": "2030c3ccaccf5d5e87916a62f5b084d6",
"assets/assets/images/payment_types/carteblanche.png": "d936e11fa3884b8c9f1bd5c914be8629",
"assets/assets/images/payment_types/discover.png": "6c0a386a00307f87db7bea366cca35f5",
"assets/assets/images/payment_types/ach.png": "7433f0aff779dc98a649b7a2daf777cf",
"assets/assets/images/payment_types/laser.png": "b4e6e93dd35517ac429301119ff05868",
"assets/assets/images/payment_types/unionpay.png": "7002f52004e0ab8cc0b7450b0208ccb2",
"assets/assets/images/payment_types/paypal.png": "8e06c094c1871376dfea1da8088c29d1",
"assets/assets/images/payment_types/jcb.png": "07e0942d16c5592118b72e74f2f7198c",
"assets/assets/images/payment_types/dinerscard.png": "06d85186ba858c18ab7c9caa42c92024",
"assets/assets/images/payment_types/mastercard.png": "6f6cdc29ee2e22e06b1ac029cb52ef71",
"assets/assets/images/icon.png": "090f69e23311a4b6d851b3880ae52541",
"assets/assets/images/google-icon.png": "0f118259ce403274f407f5e982e681c3",
"assets/assets/images/logo.png": "e5f46d5a78e226e7a9553d4ca6f69219",
"assets/AssetManifest.json": "753bba1dee0531d5fad970b5ce1d296d",
"assets/FontManifest.json": "cf3c681641169319e61b61bd0277378f",
"assets/fonts/MaterialIcons-Regular.otf": "1288c9e28052e028aba623321f7826ac",
"assets/packages/material_design_icons_flutter/lib/fonts/materialdesignicons-webfont.ttf": "174c02fc4609e8fc4389f5d21f16a296",
"assets/NOTICES": "687b68d41e137cfbdee105c0b9be3e9d",
"icons/Icon-192.png": "bb1cf5f6982006952211c7c8404ffbed",
"icons/Icon-512.png": "0f9aff01367f0a0c69773d25ca16ef35",
"favicon.png": "dca91c54388f52eded692718d5a98b8b",
"manifest.json": "ce1b79950eb917ea619a0a30da27c6a3",
"version.json": "3f9e03374a3e78d2cab3afd8723d0993",
"favicon.ico": "51636d3a390451561744c42188ccd628",
"main.dart.js": "2791c77ee5c4768ffdc2ef8aea29967c",
"/": "d389ab59423a76b2aaaa683ed382c78e"
};

// The application shell files that are downloaded before a service worker can
// start.
const CORE = [
  "/",
"main.dart.js",
"assets/NOTICES",
"assets/AssetManifest.json",
"assets/FontManifest.json"];
// During install, the TEMP cache is populated with the application shell files.
self.addEventListener("install", (event) => {
  self.skipWaiting();
  return event.waitUntil(
    caches.open(TEMP).then((cache) => {
      return cache.addAll(
        CORE.map((value) => new Request(value + '?revision=' + RESOURCES[value], {'cache': 'reload'})));
    })
  );
});

// During activate, the cache is populated with the temp files downloaded in
// install. If this service worker is upgrading from one with a saved
// MANIFEST, then use this to retain unchanged resource files.
self.addEventListener("activate", function(event) {
  return event.waitUntil(async function() {
    try {
      var contentCache = await caches.open(CACHE_NAME);
      var tempCache = await caches.open(TEMP);
      var manifestCache = await caches.open(MANIFEST);
      var manifest = await manifestCache.match('manifest');
      // When there is no prior manifest, clear the entire cache.
      if (!manifest) {
        await caches.delete(CACHE_NAME);
        contentCache = await caches.open(CACHE_NAME);
        for (var request of await tempCache.keys()) {
          var response = await tempCache.match(request);
          await contentCache.put(request, response);
        }
        await caches.delete(TEMP);
        // Save the manifest to make future upgrades efficient.
        await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
        return;
      }
      var oldManifest = await manifest.json();
      var origin = self.location.origin;
      for (var request of await contentCache.keys()) {
        var key = request.url.substring(origin.length + 1);
        if (key == "") {
          key = "/";
        }
        // If a resource from the old manifest is not in the new cache, or if
        // the MD5 sum has changed, delete it. Otherwise the resource is left
        // in the cache and can be reused by the new service worker.
        if (!RESOURCES[key] || RESOURCES[key] != oldManifest[key]) {
          await contentCache.delete(request);
        }
      }
      // Populate the cache with the app shell TEMP files, potentially overwriting
      // cache files preserved above.
      for (var request of await tempCache.keys()) {
        var response = await tempCache.match(request);
        await contentCache.put(request, response);
      }
      await caches.delete(TEMP);
      // Save the manifest to make future upgrades efficient.
      await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
      return;
    } catch (err) {
      // On an unhandled exception the state of the cache cannot be guaranteed.
      console.error('Failed to upgrade service worker: ' + err);
      await caches.delete(CACHE_NAME);
      await caches.delete(TEMP);
      await caches.delete(MANIFEST);
    }
  }());
});

// The fetch handler redirects requests for RESOURCE files to the service
// worker cache.
self.addEventListener("fetch", (event) => {
  if (event.request.method !== 'GET') {
    return;
  }
  var origin = self.location.origin;
  var key = event.request.url.substring(origin.length + 1);
  if (key.indexOf('?v=') != -1) {
    key = key.split('?v=')[0];
  }
  if (event.request.url == origin || event.request.url.startsWith(origin + '/#') || key == '') {
    key = '/';
  }
  // If the URL is not the RESOURCE list then return to signal that the
  // browser should take over.
  if (!RESOURCES[key]) {
    return;
  }
  if (key == '/') {
    return onlineFirst(event);
  }
  event.respondWith(caches.open(CACHE_NAME)
    .then((cache) =>  {
      return cache.match(event.request).then((response) => {
        // Either respond with the cached resource, or perform a fetch and
        // lazily populate the cache.
        return response || fetch(event.request).then((response) => {
          cache.put(event.request, response.clone());
          return response;
        });
      })
    })
  );
});

self.addEventListener('message', (event) => {
  // SkipWaiting can be used to immediately activate a waiting service worker.
  // This will also require a page refresh triggered by the main worker.
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
    return;
  }
  if (event.data === 'downloadOffline') {
    downloadOffline();
    return;
  }
});

// Download offline will check the RESOURCES for all files not in the cache
// and populate them.
async function downloadOffline() {
  var resources = [];
  var contentCache = await caches.open(CACHE_NAME);
  var currentContent = {};
  for (var request of await contentCache.keys()) {
    var key = request.url.substring(origin.length + 1);
    if (key == "") {
      key = "/";
    }
    currentContent[key] = true;
  }
  for (var resourceKey of Object.keys(RESOURCES)) {
    if (!currentContent[resourceKey]) {
      resources.push(resourceKey);
    }
  }
  return contentCache.addAll(resources);
}

// Attempt to download the resource online before falling back to
// the offline cache.
function onlineFirst(event) {
  return event.respondWith(
    fetch(event.request).then((response) => {
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch((error) => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.match(event.request).then((response) => {
          if (response != null) {
            return response;
          }
          throw error;
        });
      });
    })
  );
}
