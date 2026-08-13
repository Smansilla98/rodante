const shell = document.getElementById('appShell');
const openBtn = document.getElementById('navOpen');
const backdrop = document.getElementById('navBackdrop');

const closeNav = () => shell?.classList.remove('is-nav');
openBtn?.addEventListener('click', () => shell?.classList.add('is-nav'));
backdrop?.addEventListener('click', closeNav);
document.querySelectorAll('.sb-link').forEach((link) => link.addEventListener('click', closeNav));
