const hamb = document.getElementById('hamb');
const popup = document.getElementById('header_popup');
const headerNav = document.querySelectorAll('.header__navigation');
const body = document.body;

hamb.addEventListener('click', hambClickHanlder);
headerNav[1].addEventListener('click', hambClickHanlder);

function hambClickHanlder() {
  hamb.classList.toggle('_active');
  popup.classList.toggle('_active');
  body.classList.toggle('_noscroll');
}
