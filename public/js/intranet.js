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
      var overlay = document.getElementById('intranet-sidebar-overlay');

      if (!toggleBtn || !sidebar) {
         return;
      }

      // Create overlay for mobile if not exists
      if (!overlay) {
         overlay = document.createElement('div');
         overlay.id = 'intranet-sidebar-overlay';
         overlay.className = 'intranet-sidebar-overlay';
         document.body.appendChild(overlay);
      }

      // Mobile: toggle sidebar with overlay
      toggleBtn.addEventListener('click', function () {
         var isOpen = sidebar.classList.toggle('show');
         overlay.classList.toggle('show', isOpen);
         document.body.style.overflow = isOpen ? 'hidden' : '';
      });

      // Close on overlay click
      overlay.addEventListener('click', function () {
         sidebar.classList.remove('show');
         overlay.classList.remove('show');
         document.body.style.overflow = '';
      });

      // Close on Escape key
      document.addEventListener('keydown', function (event) {
         if (event.key === 'Escape' && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
         }
      });
   }

   function setText(el, text) {
      if (el) {
         el.textContent = text == null ? '' : String(text);
      }
   }
})();