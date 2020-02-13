'use strict';
const CACHE_NAME = 'flutter-app-cache';
const RESOURCES = {
  "/main.dart.js": "fa2c639a9577f242475de037e8c3c909",
"/index.html": "2b291048759a26809bc606c981d175ad",
"/assets/FontManifest.json": "280b2f61f6810d59bd1bcd4cf01d3bf4",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-regular-400.ttf": "bdd8d75eb9e6832ccd3117e06c51f0d3",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-solid-900.ttf": "d21f791b837673851dd14f7c132ef32e",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-brands-400.ttf": "3ca122272cfac33efb09d0717efde2fa",
"/assets/LICENSE": "0134b30d1e80505d7a68fb6fa8d3e2cd",
"/assets/AssetManifest.json": "90d7b27343fcf361dbf6385b3055ce4f",
"/assets/fonts/Roboto-Regular.ttf": "3e1af3ef546b9e6ecef9f3ba197bf7d2",
"/assets/fonts/MaterialIcons-Regular.ttf": "56d3ffdef7a25659eab6a68a3fbfaf16",
"/assets/assets/images/logo.png": "090f69e23311a4b6d851b3880ae52541",
"/assets/web/assets/fonts/Roboto-Regular.ttf": "3e1af3ef546b9e6ecef9f3ba197bf7d2"
};

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (cacheName) {
      return caches.delete(cacheName);
    }).then(function (_) {
      return caches.open(CACHE_NAME);
    }).then(function (cache) {
      return cache.addAll(Object.keys(RESOURCES));
    })
  );
});

self.addEventListener('fetch', function (event) {
  event.respondWith(
    caches.match(event.request)
      .then(function (response) {
        if (response) {
          return response;
        }
        return fetch(event.request, {
          credentials: 'include'
        });
      })
  );
});
