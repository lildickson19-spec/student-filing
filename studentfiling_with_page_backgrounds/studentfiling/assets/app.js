// Small helpers: confirm dialogs and the mobile menu
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !confirm(msg)) e.preventDefault();
});
var menuBtn = document.getElementById('menuBtn');
if (menuBtn) menuBtn.addEventListener('click', function () {
  document.getElementById('side').classList.toggle('open');
});
