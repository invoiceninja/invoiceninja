'use strict';
const CACHE_NAME = 'flutter-app-cache';
const RESOURCES = {
  "/favicon.ico": "51636d3a390451561744c42188ccd628",
"/assets/AssetManifest.json": "90d7b27343fcf361dbf6385b3055ce4f",
"/assets/LICENSE": "51e1ca5d694051f18a7d23235a7f4837",
"/assets/assets/images/logo.png": "090f69e23311a4b6d851b3880ae52541",
"/assets/web/assets/fonts/Roboto-Regular.ttf": "3e1af3ef546b9e6ecef9f3ba197bf7d2",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-solid-900.ttf": "0ea892e09437fcaa050b2b15c53173b7",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-regular-400.ttf": "d51b09f7b8345b41dd3b2201f653c62b",
"/assets/packages/font_awesome_flutter/lib/fonts/fa-brands-400.ttf": "51d23d1c30deda6f34673e0d5600fd38",
"/assets/fonts/Roboto-Regular.ttf": "3e1af3ef546b9e6ecef9f3ba197bf7d2",
"/assets/fonts/MaterialIcons-Regular.ttf": "56d3ffdef7a25659eab6a68a3fbfaf16",
"/assets/FontManifest.json": "280b2f61f6810d59bd1bcd4cf01d3bf4",
"/main.dart.js": "8367a4450d996103f9aef2a380bb1a56",
"/index.html": "02f52d8ed33971b0d7a938e631e89401"
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
        return fetch(event.request);
      })
  );
});
