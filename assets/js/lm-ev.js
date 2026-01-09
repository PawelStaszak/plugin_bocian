(function(){
  function post(data){
    return fetch(LM_EV.ajax_url, {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(data).toString(),
      credentials: 'same-origin'
    }).then(r => r.json());
  }

  function addDays(dateStr, days){
    const [y,m,d] = dateStr.split('-').map(n => parseInt(n,10));
    const dt = new Date(Date.UTC(y, m-1, d, 12, 0, 0));
    dt.setUTCDate(dt.getUTCDate() + days);
    const yy = dt.getUTCFullYear();
    const mm = String(dt.getUTCMonth()+1).padStart(2,'0');
    const dd = String(dt.getUTCDate()).padStart(2,'0');
    return `${yy}-${mm}-${dd}`;
  }

  function setRangeFromStart(){
    const startEl = document.getElementById('lm-ev-start');
    const endEl   = document.getElementById('lm-ev-end');
    if(!startEl || !endEl || !window.LM_EV_UI) return;

    const start = startEl.value || '';
    if(!start){
      endEl.value = '';
      return;
    }

    const nights = parseInt(window.LM_EV_UI.requiredNights || 3, 10);
    const end = addDays(start, nights);
    endEl.value = end;

    post({ action:'lm_ev_set_date_range', start:start, end:end, nonce:LM_EV.nonce })
      .then(function(res){
        if(!res || !res.ok){
          alert((res && res.message) ? res.message : 'Błąd zapisu dat.');
          startEl.value = '';
          endEl.value = '';
        }
      })
      .catch(function(){
        alert('Błąd połączenia przy zapisie dat.');
      });
  }

  document.addEventListener('change', function(e){
    if (e.target && e.target.classList.contains('lm-addon')) {
      const pid = e.target.getAttribute('data-product-id');
      const checked = e.target.checked ? 1 : 0;

      post({ action: 'lm_toggle_addon', product_id: pid, checked: checked, nonce: LM_EV.nonce })
        .then(function(res){
          if(res && res.ok){
            const box = document.getElementById('lm-totals');
            if (box) box.innerHTML = res.totals_html;

            if (window.LM_EV_UI) {
              if (typeof res.weekend_unlocked !== 'undefined') window.LM_EV_UI.weekendUnlocked = parseInt(res.weekend_unlocked,10);
              if (typeof res.required_nights !== 'undefined') window.LM_EV_UI.requiredNights = parseInt(res.required_nights,10);
            }

            setRangeFromStart();
          } else {
            alert((res && res.message) ? res.message : 'Błąd.');
            window.location.reload();
          }
        })
        .catch(function(){
          alert('Błąd połączenia.');
          window.location.reload();
        });
    }

    if (e.target && e.target.id === 'lm-ev-start') {
      setRangeFromStart();
    }
  });

  document.addEventListener('click', function(e){
    const t = e.target;
    if (t && t.id === 'lm-reset') {
      e.preventDefault();
      post({ action: 'lm_reset_employee_flow', nonce: LM_EV.nonce })
        .then(function(){ window.location.reload(); })
        .catch(function(){ window.location.reload(); });
    }
  });

  document.addEventListener('DOMContentLoaded', function(){
    const startEl = document.getElementById('lm-ev-start');
    const endEl   = document.getElementById('lm-ev-end');
    if(startEl && endEl && startEl.value && !endEl.value){
      setRangeFromStart();
    }
  });
})();

document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'undefined') return;

    const input = document.getElementById('lm-ev-calendar-front');
    if (!input || !window.LM_EV_UI || !window.LM_EV_DATES) return;

    function isWeekend(date) {
        const d = date.getDay();
        return d === 0 || d === 6;
    }

    function initCalendar() {
        if (input._flatpickr) {
            input._flatpickr.destroy();
        }

        flatpickr(input, {
            dateFormat: 'Y-m-d',
            enable: LM_EV_DATES.allowed || [],

            disable:
                LM_EV_UI.weekendLockedDefault && !LM_EV_UI.weekendUnlocked
                    ? [isWeekend]
                    : [],

            onChange: function (dates) {
                if (!dates.length) return;

                const startDate = dates[0];
                const requiredNights = parseInt(LM_EV_UI.requiredNights, 10);

                let cursor = new Date(startDate);
                let valid = true;

                for (let i = 0; i < requiredNights; i++) {
                    if (
                        LM_EV_UI.weekendLockedDefault &&
                        !LM_EV_UI.weekendUnlocked &&
                        isWeekend(cursor)
                    ) {
                        valid = false;
                        break;
                    }
                    cursor.setDate(cursor.getDate() + 1);
                }

                if (!valid) {
                    alert('Ten termin wymaga dodatku weekend.');
                    input._flatpickr.clear();
                    return;
                }

                const startStr = startDate.toISOString().slice(0, 10);
                const endStr   = cursor.toISOString().slice(0, 10);

                document.getElementById('lm-ev-date-start').value = startStr;
                document.getElementById('lm-ev-date-end').value   = endStr;

                const info = document.getElementById('lm-ev-date-info');
                if (info) {
                    info.innerHTML =
                        `Pobyt: <strong>${startStr}</strong> → <strong>${endStr}</strong> (${requiredNights} nocy)`;
                }

                fetch(LM_EV.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'lm_ev_set_date_range',
                        nonce: LM_EV.nonce,
                        start: startStr,
                        end: endStr
                    })
                });
            }
        });
    }

    initCalendar();

    // 🔁 Reakcja na zmianę dodatków (weekend / upgrade 3→5)
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('lm-addon')) {
            setTimeout(function () {
                initCalendar();
            }, 150);
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'undefined') return;

    const cfg = window.LM_EV_DATES || {};
    const input = document.getElementById('lm-ev-calendar-front');
    if (!input) return;

    flatpickr(input, {
        dateFormat: 'Y-m-d',
        enable: cfg.allowed || [],
        onChange: function (dates) {
            if (!dates.length) return;

            const start = dates[0];
            let cur = new Date(start);
            let nights = 0;

            while (nights < cfg.requiredNights) {
                cur.setDate(cur.getDate() + 1);

                const dow = cur.getDay(); // 0 = nd, 6 = sob
                if (cfg.weekendLocked && !cfg.weekendUnlocked && (dow === 0 || dow === 6)) {
                    alert('Weekend jest niedozwolony. Dodaj opcję weekend.');
                    input._flatpickr.clear();
                    return;
                }
                nights++;
            }

            const startStr = start.toISOString().slice(0, 10);
            const endStr = cur.toISOString().slice(0, 10);

            document.getElementById('lm-ev-date-start').value = startStr;
            document.getElementById('lm-ev-date-end').value = endStr;

            document.getElementById('lm-ev-date-info').innerHTML =
                `Pobyt: <strong>${startStr}</strong> → <strong>${endStr}</strong> (${cfg.requiredNights} nocy)`;

            fetch(LM_EV.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'lm_ev_set_date_range',
                    nonce: LM_EV.nonce,
                    start: startStr,
                    end: endStr
                })
            });
        }
    });
});
