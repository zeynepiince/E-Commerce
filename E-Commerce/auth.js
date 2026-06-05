(function () {
  'use strict';

  const tabs = document.querySelectorAll('.auth-tab');
  const forms = document.querySelectorAll('.auth-form');
  const signinForm = document.getElementById('signin-form');
  const joinForm = document.getElementById('join-form');
  const forgotForm = document.getElementById('forgot-form');
  const socialBlock = document.getElementById('auth-page-social');
  const authHeader = document.querySelector('.auth-header');
  const defaultTitle = authHeader ? authHeader.querySelector('.auth-card-title') : null;
  const defaultSubtitle = authHeader ? authHeader.querySelector('.auth-card-subtitle') : null;
  const savedTitle = defaultTitle ? defaultTitle.textContent : '';
  const savedSubtitle = defaultSubtitle ? defaultSubtitle.textContent : '';

  function setAuthHeader(title, subtitle) {
    if (defaultTitle) defaultTitle.textContent = title;
    if (defaultSubtitle) defaultSubtitle.textContent = subtitle;
  }

  function switchTab(target) {
    const isJoin = target === 'join';
    const isForgot = target === 'forgot';

    tabs.forEach(function (tab) {
      tab.classList.toggle('active', !isForgot && tab.dataset.tab === target);
    });

    if (tabs.length) {
      tabs.forEach(function (tab) {
        tab.style.display = isForgot ? 'none' : '';
      });
    }

    forms.forEach(function (form) {
      form.classList.remove('active');
      if (form.id === 'join-form') {
        form.classList.toggle('join-active', isJoin);
      }
    });

    if (socialBlock) {
      socialBlock.style.display = isForgot ? 'none' : '';
    }

    if (isForgot) {
      setAuthHeader(
        document.documentElement.lang === 'tr' ? 'Şifrenizi mi unuttunuz?' : 'Forgot your password?',
        document.documentElement.lang === 'tr'
          ? 'E-posta adresinizi girin, size sıfırlama bağlantısı gönderelim.'
          : 'Enter your email and we will send you a reset link.'
      );
      if (forgotForm) forgotForm.classList.add('active');
      return;
    }

    setAuthHeader(savedTitle, savedSubtitle);

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

  const activeTab = document.querySelector('.auth-tab.active');
  if (activeTab && activeTab.dataset.tab === 'join') {
    switchTab('join');
  }
})();
