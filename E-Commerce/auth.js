(function () {
  'use strict';

  const tabs = document.querySelectorAll('.auth-tab');
  const forms = document.querySelectorAll('.auth-form');
  const signinForm = document.getElementById('signin-form');
  const joinForm = document.getElementById('join-form');

  function switchTab(target) {
    const isJoin = target === 'join';

    tabs.forEach(function (tab) {
      tab.classList.toggle('active', tab.dataset.tab === target);
    });

    forms.forEach(function (form) {
      form.classList.remove('active');
      if (form.id === 'join-form') {
        form.classList.toggle('join-active', isJoin);
      }
    });

    requestAnimationFrame(function () {
      const activeForm = isJoin ? joinForm : signinForm;
      if (activeForm) activeForm.classList.add('active');
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      switchTab(tab.dataset.tab);
    });
  });

  document.querySelectorAll('.auth-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      switchTab(link.dataset.switch);
    });
  });

  // Password visibility toggle
  document.querySelectorAll('.input-toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const group = btn.closest('.input-group--password');
      const input = group ? group.querySelector('input[type="password"], input[type="text"]') : null;
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.classList.toggle('visible', isPassword);
    });
  });

  // Sign In form
  if (signinForm) {
    signinForm.addEventListener('submit', function () {
      // Form submits via POST to auth.php
    });
  }

  // Join form - client-side validation
  if (joinForm) {
    joinForm.addEventListener('submit', function (e) {
      const password = document.getElementById('join-password');
      const confirm = document.getElementById('join-confirm');

      if (!password || !confirm) return;

      if (password.value !== confirm.value) {
        e.preventDefault();
        alert('Passwords do not match.');
        return;
      }

      if (password.value.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters.');
        return;
      }
    });
  }

  // Forgot password link
  const forgotLink = document.querySelector('.forgot-link');
  if (forgotLink) {
    forgotLink.addEventListener('click', function (e) {
      e.preventDefault();
      alert('Forgot password functionality coming soon.');
    });
  }

  // Set initial tab state from PHP
  const activeTab = document.querySelector('.auth-tab.active');
  if (activeTab && activeTab.dataset.tab === 'join') {
    switchTab('join');
  }
})();
