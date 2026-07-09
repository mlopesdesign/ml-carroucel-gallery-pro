
(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var wrap = document.querySelector('.mlpb-admin-wrap');
    if(!wrap){ return; }
    var toastArea = document.getElementById('mlpb-toast-area');
    function showToast(message, type){
      if(!toastArea || !message){ return; }
      var toast = document.createElement('div');
      toast.className = 'mlpb-toast mlpb-toast-' + (type || 'success');
      toast.textContent = message;
      toastArea.appendChild(toast);
      requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
      setTimeout(function(){ toast.classList.remove('is-visible'); setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 220); }, 4200);
    }
    var initialToast = wrap.getAttribute('data-toast-message');
    var initialType = wrap.getAttribute('data-toast-type') || 'success';
    if(initialToast){ showToast(initialToast, initialType); }
    document.querySelectorAll('.mlpb-tab-button').forEach(function(button){
      button.addEventListener('click', function(){
        var target = button.getAttribute('data-tab-target');
        if(!target){ return; }
        document.querySelectorAll('.mlpb-tab-button').forEach(function(btn){ btn.classList.remove('is-active'); });
        document.querySelectorAll('.mlpb-tab-panel').forEach(function(panel){ panel.hidden = true; panel.classList.remove('is-active'); });
        button.classList.add('is-active');
        var panel = document.getElementById(target);
        if(panel){ panel.hidden = false; panel.classList.add('is-active'); }
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', target.replace('mlpb-tab-', ''));
          window.history.replaceState({}, '', url.toString());
        } catch(e){}
      });
    });
    document.querySelectorAll('[data-copy-target]').forEach(function(button){
      button.addEventListener('click', function(){
        var selector = button.getAttribute('data-copy-target');
        var field = document.querySelector(selector);
        if(!field){ return; }
        var text = field.value || field.textContent || '';
        navigator.clipboard.writeText(text).then(function(){ showToast('Prompt copiado.', 'success'); }).catch(function(){ showToast('Falha ao copiar.', 'error'); });
      });
    });
  });
})();
document.addEventListener('DOMContentLoaded', function(){
  var range = document.getElementById('mlcgp_overlay_opacity');
  var rangeValue = document.getElementById('mlcgp_overlay_value');
  if(range && rangeValue){
    var syncRange = function(){ rangeValue.textContent = range.value + '%'; };
    range.addEventListener('input', syncRange);
    syncRange();
  }
  var profilesWrap = document.getElementById('mlcgp-profiles');
  var addBtn = document.querySelector('.mlcgp-add-profile');
  var tmpl = document.getElementById('tmpl-mlcgp-profile-row');
  function refreshProfileState(card){
    var source = card.querySelector('.mlcgp-profile-source');
    if(!source) return;
    var sourceValue = source.value || 'all';
    var albumWrap = card.querySelector('.mlcgp-source-album');
    var galleriesWrap = card.querySelector('.mlcgp-source-galleries');
    if(albumWrap) albumWrap.classList.toggle('is-hidden', sourceValue !== 'album');
    if(galleriesWrap) galleriesWrap.classList.toggle('is-hidden', sourceValue !== 'galleries');
    var idInput = card.querySelector('input[name*="[id]"]');
    var code = card.querySelector('.mlcgp-profile-shortcode code');
    var title = card.querySelector('.mlcgp-profile-card__head strong');
    var label = card.querySelector('input[name*="[label]"]');
    if(code && idInput) code.textContent = '[ml_carousel_gallery id="' + (idInput.value || 'seu-id') + '"]';
    if(title && label) title.textContent = label.value || 'Novo carrossel';
  }
  function bindCard(card){
    card.querySelectorAll('.mlcgp-profile-source').forEach(function(select){ select.addEventListener('change', function(){ refreshProfileState(card); }); });
    card.querySelectorAll('input[name*="[id]"], input[name*="[label]"]').forEach(function(field){ field.addEventListener('input', function(){ refreshProfileState(card); }); });
    var removeBtn = card.querySelector('.mlcgp-remove-profile');
    if(removeBtn){ removeBtn.addEventListener('click', function(){ card.remove(); }); }
    refreshProfileState(card);
  }
  if(profilesWrap){ profilesWrap.querySelectorAll('.mlcgp-profile-card').forEach(bindCard); }
  if(profilesWrap && addBtn && tmpl){ addBtn.addEventListener('click', function(){ var count = profilesWrap.querySelectorAll('.mlcgp-profile-card').length; var html = tmpl.innerHTML.replace(/\{\{index\}\}/g, String(count)); var temp = document.createElement('div'); temp.innerHTML = html.trim(); var card = temp.firstElementChild; if(!card) return; profilesWrap.appendChild(card); bindCard(card); }); }
});

(function(){
  function clamp(num, min, max){ return Math.min(max, Math.max(min, num)); }
  function one(selector){ return document.querySelector(selector); }
  function all(selector){ return Array.prototype.slice.call(document.querySelectorAll(selector)); }
  document.addEventListener('DOMContentLoaded', function(){
    var stage = one('#mlcgp-preview-stage');
    var wrapper = one('#mlcgp-preview-wrapper');
    if(!stage || !wrapper){ return; }
    var readout = one('#mlcgp-preview-readout');
    var track = one('.mlcgp-preview-track');
    var slides = all('.mlcgp-preview-slide');
    var device = 'desktop';
    var currentIndex = 0;
    var autoplayTimer = null;

    function value(name, fallback){
      var field = document.querySelector('[name="mlcgp_settings[' + name + ']"]');
      if(!field){ return fallback; }
      if(field.type === 'checkbox'){ return field.checked ? '1' : '0'; }
      return field.value || fallback;
    }

    function visibleCount(){
      if(device === 'tablet'){ return clamp(parseFloat(value('visible_tablet', '2')) || 2, 1, 4); }
      if(device === 'mobile'){ return clamp(parseFloat(value('visible_mobile', '1')) || 1, 1, 2); }
      return clamp(parseFloat(value('visible_desktop', '3.5')) || 3.5, 1, 6);
    }

    function maxIndex(){
      var visibleSlots = Math.max(1, Math.floor(visibleCount()));
      return Math.max(0, slides.length - visibleSlots);
    }

    function setClassByPrefix(prefix, current){
      wrapper.className = wrapper.className.replace(new RegExp('\\b' + prefix + '-(top|center|bottom|left|right)\\b', 'g'), '').replace(/\s+/g, ' ').trim();
      wrapper.classList.add(prefix + '-' + current);
    }

    function syncCenterState(){
      var centerMode = value('center_mode', '0') === '1';
      slides.forEach(function(slide){ slide.classList.remove('is-center'); });
      if(!centerMode || !slides.length){ return; }
      var centerOffset = Math.floor(visibleCount() / 2);
      var centerIndex = Math.min(slides.length - 1, currentIndex + centerOffset);
      if(slides[centerIndex]){ slides[centerIndex].classList.add('is-center'); }
    }

    function syncTrack(){
      if(!track){ return; }
      var gap = clamp(parseInt(value('card_gap', '10'), 10) || 0, 0, 80);
      var slideWidth = slides.length ? slides[0].getBoundingClientRect().width : 0;
      var offset = (slideWidth + gap) * currentIndex;
      track.style.transform = 'translate3d(' + (-offset) + 'px,0,0)';
      syncCenterState();
    }

    function stopAutoplay(){
      if(autoplayTimer){
        clearInterval(autoplayTimer);
        autoplayTimer = null;
      }
    }

    function startAutoplay(){
      stopAutoplay();
      if(!track || slides.length <= 1){ return; }
      var endIndex = maxIndex();
      if(endIndex <= 0){ return; }
      var speed = clamp(parseInt(value('speed', '4000'), 10) || 4000, 1200, 15000);
      autoplayTimer = window.setInterval(function(){
        var lastIndex = maxIndex();
        currentIndex = currentIndex >= lastIndex ? 0 : currentIndex + 1;
        syncTrack();
      }, speed);
    }

    function syncPreview(){
      var gap = clamp(parseInt(value('card_gap', '10'), 10) || 0, 0, 80);
      var width = clamp(parseInt(value('card_width', '0'), 10) || 0, 0, 1200);
      var height = clamp(parseInt(value('card_height', '280'), 10) || 0, 0, 800);
      var overlay = clamp(parseInt(value('overlay_opacity', '55'), 10) || 0, 0, 100);
      var desktop = clamp(parseFloat(value('visible_desktop', '3.5')) || 3.5, 1, 6);
      var tablet = clamp(parseFloat(value('visible_tablet', '2')) || 2, 1, 4);
      var mobile = clamp(parseFloat(value('visible_mobile', '1')) || 1, 1, 2);
      var position = value('text_position', 'bottom');
      var align = value('text_align', 'center');
      var showDate = value('show_date', '1') === '1';
      var centerMode = value('center_mode', '0') === '1';
      var visible = visibleCount();
      wrapper.style.setProperty('--mlcgp-gap', gap + 'px');
      wrapper.style.setProperty('--mlcgp-card-width', width + 'px');
      wrapper.style.setProperty('--mlcgp-card-height', height + 'px');
      wrapper.style.setProperty('--mlcgp-overlay-opacity', String(overlay / 100));
      wrapper.style.setProperty('--mlcgp-visible-desktop', String(desktop));
      wrapper.style.setProperty('--mlcgp-visible-tablet', String(tablet));
      wrapper.style.setProperty('--mlcgp-visible-mobile', String(mobile));
      wrapper.style.setProperty('--mlcgp-preview-visible', String(visible));
      wrapper.classList.toggle('mlcgp-center-mode', centerMode);
      setClassByPrefix('mlcgp-text', position);
      setClassByPrefix('mlcgp-align', align);
      all('.mlcgp-preview-date').forEach(function(el){
        var shouldHide = !showDate || !String(el.textContent || '').trim();
        el.classList.toggle('is-hidden', shouldHide);
      });
      all('.mlcgp-preview-card').forEach(function(card){
        if(height > 0){
          card.style.height = height + 'px';
          card.style.aspectRatio = 'unset';
        } else {
          card.style.height = '';
          card.style.aspectRatio = '';
        }
        if(width > 0){
          card.style.width = width + 'px';
          card.style.maxWidth = width + 'px';
        } else {
          card.style.width = '';
          card.style.maxWidth = '';
        }
      });
      currentIndex = Math.min(currentIndex, maxIndex());
      stage.className = 'mlcgp-preview-stage device-' + device;
      if(readout){ readout.textContent = (device.charAt(0).toUpperCase() + device.slice(1)) + ' · ' + visible + ' cards'; }
      syncTrack();
      startAutoplay();
    }

    all('.mlcgp-preview-device').forEach(function(button){
      button.addEventListener('click', function(){
        device = button.getAttribute('data-device') || 'desktop';
        currentIndex = 0;
        all('.mlcgp-preview-device').forEach(function(btn){ btn.classList.remove('is-active'); });
        button.classList.add('is-active');
        syncPreview();
      });
    });

    ['limit','autoplay','speed','text_position','text_align','new_tab','show_date','card_gap','card_width','card_height','center_mode','overlay_opacity','visible_desktop','visible_tablet','visible_mobile'].forEach(function(name){
      var field = document.querySelector('[name="mlcgp_settings[' + name + ']"]');
      if(!field){ return; }
      field.addEventListener('input', syncPreview);
      field.addEventListener('change', syncPreview);
    });

    wrapper.addEventListener('mouseenter', stopAutoplay);
    wrapper.addEventListener('mouseleave', startAutoplay);
    window.addEventListener('resize', syncTrack);
    document.addEventListener('visibilitychange', function(){
      if(document.hidden){ stopAutoplay(); }
      else { startAutoplay(); }
    });

    syncPreview();
  });
})();

(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var wrap = document.querySelector('.mlpb-admin-wrap');
    if(!wrap){ return; }
    var toastArea = document.getElementById('mlpb-toast-area');
    function showToast(message, type){
      if(!toastArea || !message){ return; }
      var toast = document.createElement('div');
      toast.className = 'mlpb-toast mlpb-toast-' + (type || 'success');
      toast.textContent = message;
      toastArea.appendChild(toast);
      requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
      setTimeout(function(){ toast.classList.remove('is-visible'); setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 220); }, 4200);
    }

    document.querySelectorAll('.mlcgp-refresh-covers').forEach(function(btn){
      btn.addEventListener('click', function(){
        var profile = btn.getAttribute('data-profile') || '';
        var nonce   = (typeof mlcgpAdmin !== 'undefined' && mlcgpAdmin.nonce) ? mlcgpAdmin.nonce : (btn.getAttribute('data-nonce') || '');
        var ajaxUrl = (typeof mlcgpAdmin !== 'undefined' && mlcgpAdmin.ajaxUrl) ? mlcgpAdmin.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

        btn.disabled = true;
        btn.textContent = 'Atualizando…';

        var body = new URLSearchParams();
        body.append('action', 'mlcgp_refresh_covers');
        body.append('_ajax_nonce', nonce);
        body.append('profile', profile);

        fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if(data && data.success){
            showToast('Capas atualizadas', 'success');
          } else {
            showToast('Erro ao atualizar capas', 'error');
          }
        })
        .catch(function(){
          showToast('Erro ao atualizar capas', 'error');
        })
        .finally(function(){
          btn.disabled = false;
          btn.textContent = 'Atualizar capas';
        });
      });
    });
  });
})();
