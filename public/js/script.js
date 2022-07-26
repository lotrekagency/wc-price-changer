function startAnimation(){
  notice = document.getElementById('div-table-jobs');
  notice.classList.toggle('div-table-jobs-active');

  element = document.getElementById('link-activities');
  label1 = 'View all actions';
  label2 = 'Hide activities'
  if(element.textContent == label1) element.textContent = label2;
  else element.textContent = label1;
}
