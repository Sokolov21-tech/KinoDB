
document.getElementById('password')?.addEventListener('input', function() {
  const v = this.value;
  let score = 0;
  if (v.length >= 8)          score++;
  if (/[A-Z]/.test(v))        score++;
  if (/[0-9]/.test(v))        score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  if (v.length >= 16)          score++;

  const bar   = document.getElementById('pw-strength-bar');
  const label = document.getElementById('pw-strength-label');
  const configs = [
    { pct: '0%',   color: '',                  text: '' },
    { pct: '25%',  color: 'var(--red-bright)', text: 'Очень слабый' },
    { pct: '50%',  color: '#E8943C',           text: 'Слабый' },
    { pct: '75%',  color: 'var(--amber)',      text: 'Хороший' },
    { pct: '90%',  color: 'var(--green)',      text: 'Надёжный' },
    { pct: '100%', color: 'var(--green)',      text: 'Отличный' },
  ];
  const cfg = configs[Math.min(score, 5)];
  if (bar)   { bar.style.width = v ? cfg.pct : '0%'; bar.style.background = cfg.color; }
  if (label)   label.textContent = v ? cfg.text : '';
});


document.getElementById('password2')?.addEventListener('input', function() {
  const match = this.value === document.getElementById('password')?.value;
  this.classList.toggle('error', !match && this.value.length > 0);
  document.getElementById('err-password2')?.classList.toggle('show', !match && this.value.length > 0);
});
