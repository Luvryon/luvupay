/**
 * AI Retention Campaign Studio — Alpine factory (hard security: no inline x-data JS).
 * Never put large JS objects in HTML attributes; double quotes break the page.
 */
(function (global) {
  'use strict';

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.getAttribute('content') : '';
  }

  global.campaignStudio = function campaignStudio() {
    return {
      discount: 20,
      selectedImage: 0,
      customImage: null,
      images: [
        { id: 0, label: 'Warm abstract', color: '#fef3c7' },
        { id: 1, label: 'Product lifestyle', color: '#e0e7ff' },
        { id: 2, label: 'Loyalty badge', color: '#ede9fe' },
      ],
      sending: false,
      sent: false,
      generating: false,
      subject: 'We miss you. Keep your access active',
      bodyLead: 'You have been with us for a long time. Your last payment failed, but your access can still be saved.',
      previewName: 'there',
      brand: 'your plan',
      tone: 'Empathetic (loyal user)',

      onFile: function (e) {
        var f = e.target.files && e.target.files[0];
        if (!f) return;
        var self = this;
        var reader = new FileReader();
        reader.onload = function (ev) {
          self.customImage = ev.target.result;
          self.selectedImage = -1;
        };
        reader.readAsDataURL(f);
      },

      regenerateCopy: async function () {
        this.generating = true;
        // Soft wait only for UX pulse; replace with real Nebius call when wired
        await new Promise(function (r) { setTimeout(r, 400); });
        var variants = [
          {
            s: 'Important: keep your ' + this.brand + ' access',
            b: 'Hi ' + this.previewName + ', we noticed a payment issue. Renew now with ' + this.discount + '% off to keep uninterrupted access.',
          },
          {
            s: 'We miss you, ' + this.previewName,
            b: 'Hi ' + this.previewName + ', your plan is still recoverable. As a valued member you get ' + this.discount + '% off if you renew today.',
          },
          {
            s: this.previewName + ', a quick way to restore access',
            b: 'Payment failed once. Finish in one click and keep your benefits. Offer: ' + this.discount + '% off for 24 hours.',
          },
        ];
        var v = variants[Math.floor(Math.random() * variants.length)];
        this.subject = v.s;
        this.bodyLead = v.b;
        this.generating = false;
      },

      approveAndSend: async function () {
        if (this.sending || this.sent) return;
        this.sending = true;
        if (global.LuvuLoader && global.LuvuLoader.begin) global.LuvuLoader.begin();
        try {
          var res = await fetch('/api/v1/dashboard/demo/retention-campaign', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
              subject: this.subject,
              body: this.bodyLead || '',
              discount: this.discount,
              tone: this.tone || 'empathetic',
              image_data_url: this.customImage || null,
              cohort: 'failed_and_refund',
            }),
          });
          var data = await res.json().catch(function () { return {}; });
          this.sending = false;
          if (!res.ok || data.ok === false) {
            if (global.LuvuToast) {
              global.LuvuToast.error('Send failed', data.message || 'Check mail config');
            }
            return;
          }
          this.sent = true;
          if (global.LuvuToast) {
            global.LuvuToast.success(
              'Campaign sent',
              data.message || ('Sent to ' + (data.sent || 0) + ' users')
            );
          }
        } catch (e) {
          this.sending = false;
          if (global.LuvuToast) {
            global.LuvuToast.error('Send failed', String(e.message || e));
          }
        } finally {
          if (global.LuvuLoader && global.LuvuLoader.end) global.LuvuLoader.end();
        }
      },
    };
  };
})(window);
