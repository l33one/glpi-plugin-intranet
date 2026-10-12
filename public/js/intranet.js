/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * Vanilla JS (no extra dependency): delete confirmations, announcement
 * reader modal, and sidebar toggle.
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     2026
 *
 * -------------------------------------------------------------------------
 */
(function () {
   'use strict';

   function onReady(fn) {
      if (document.readyState !== 'loading') {
         fn();
      } else {
         document.addEventListener('DOMContentLoaded', fn);
      }
   }

   // Map announcement category keys to Bootstrap badge classes (mirrors the
   // PHP map in PluginIntranetPortal::categoryBadgeClass()).
   var CATEGORY_BADGES = {
      notice: 'bg-info',
      news: 'bg-primary',
      maintenance: 'bg-warning',
      event: 'bg-success'
   };

   onReady(function () {
      // Delete confirmations on the settings page.
      document.querySelectorAll('form[data-intranet-confirm]').forEach(function (form) {
         form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-intranet-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
               event.preventDefault();
            }
         });
      });

      initAnnouncementReader();
      initSidebarToggle();
   });

   function initAnnouncementReader() {
      var container = document.querySelector('[data-intranet-ajax]');
      if (!container) {
         return;
      }
      var endpoint = container.getAttribute('data-intranet-ajax');
      if (!endpoint) {
         return;
      }
      var modalEl = document.getElementById('intranet-announcement-modal');
      if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
         return;
      }
      var modal = new bootstrap.Modal(modalEl);
      var titleEl = document.getElementById('intranet-announcement-title');
      var categoryEl = document.getElementById('intranet-announcement-category');
      var metaEl = document.getElementById('intranet-announcement-meta');
      var bodyEl = document.getElementById('intranet-announcement-body');

      container.addEventListener('click', function (event) {
         var item = event.target.closest('[data-intranet-announcement]');
         if (!item || !container.contains(item)) {
            return;
         }
         var id = parseInt(item.getAttribute('data-intranet-announcement'), 10);
         if (!id) {
            return;
         }
         openAnnouncement(endpoint, id, modal, titleEl, categoryEl, metaEl, bodyEl);
      });
   }

   function openAnnouncement(endpoint, id, modal, titleEl, categoryEl, metaEl, bodyEl) {
      setText(titleEl, '...');
      setText(metaEl, '');
      setText(categoryEl, '');
      setText(bodyEl, '');
      modal.show();

      fetch(endpoint + '?id=' + encodeURIComponent(id), {
         headers: { Accept: 'application/json' },
         credentials: 'same-origin'
      })
         .then(function (response) {
            return response.json().then(function (payload) {
               return { status: response.status, payload: payload };
            });
         })
         .then(function (result) {
            var payload = result.payload || {};
            if (!payload.success || !payload.announcement) {
               setText(titleEl, '');
               setText(bodyEl, payload.message || 'Error');
               return;
            }
            var ann = payload.announcement;
            setText(titleEl, ann.title || '');
            setText(categoryEl, ann.category || '');
            categoryEl.className = 'badge ' + (CATEGORY_BADGES[ann.category_key] || 'bg-primary');
            var meta = [];
            if (ann.date) {
               meta.push(ann.date);
            }
            if (ann.author) {
               meta.push(ann.author);
            }
            setText(metaEl, meta.join(' · '));
            setText(bodyEl, ann.content || '');
         })
         .catch(function () {
            setText(titleEl, '');
            setText(bodyEl, 'Error');
         });
   }

   function initSidebarToggle() {
      var toggleBtn = document.querySelector('.intranet-sidebar-toggle');
      var sidebar = document.querySelector('.intranet-sidebar');
      var appBody = document.querySelector('.intranet-app-body');

      if (!toggleBtn || !sidebar || !appBody) {
         return;
      }

      // Restore state from localStorage
      var collapsed = localStorage.getItem('intranet_sidebar_collapsed') === 'true';
      if (collapsed) {
         sidebar.classList.add('collapsed');
         appBody.classList.add('sidebar-collapsed');
      }

      toggleBtn.addEventListener('click', function () {
         var isCollapsed = sidebar.classList.toggle('collapsed');
         appBody.classList.toggle('sidebar-collapsed');
         localStorage.setItem('intranet_sidebar_collapsed', isCollapsed);
      });

      // Mobile: click outside to close
      document.addEventListener('click', function (event) {
         if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
            if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
               sidebar.classList.remove('show');
            }
         }
      });
   }

   function setText(el, text) {
      if (el) {
         el.textContent = text == null ? '' : String(text);
      }
   }
})();