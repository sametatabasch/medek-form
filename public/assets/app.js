(function () {
  'use strict';
  function grow(t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }
  function initGrow(root) { (root || document).querySelectorAll('textarea.auto').forEach(function (t) { grow(t); }); }
  document.addEventListener('input', function (e) { if (e.target.matches && e.target.matches('textarea.auto')) grow(e.target); });
  initGrow();

  var form = document.getElementById('mform'), dirty = false;
  if (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  function renumber(table) {
    table.querySelectorAll('tbody tr').forEach(function (tr, i) { var n = tr.querySelector('td.no'); if (n) n.textContent = i + 1; });
  }
  document.addEventListener('click', function (e) {
    var add = e.target.closest && e.target.closest('.add-row');
    if (add) {
      var wrap = add.closest('.tablewrap'), table = wrap.querySelector('table'), tpl = wrap.querySelector('template');
      var idx = Date.now().toString(36) + Math.floor(Math.random() * 1000);
      var tmp = document.createElement('tbody'); tmp.innerHTML = tpl.innerHTML.replace(/__I__/g, idx);
      var tr = tmp.firstElementChild; table.tBodies[0].appendChild(tr); renumber(table); initGrow(tr);
      var f = tr.querySelector('textarea,input,select'); if (f) f.focus(); dirty = true; return;
    }
    var del = e.target.closest && e.target.closest('.del-row');
    if (del) {
      var t = del.closest('table'), tr2 = del.closest('tr');
      if (t.tBodies[0].rows.length > 1 || confirm('Son satır silinsin mi?')) { tr2.remove(); renumber(t); dirty = true; }
    }
  });
})();
