function startAnimation(){
  const notice = document.getElementById('div-table-jobs');
  notice.classList.toggle('div-table-jobs-active');

  const element = document.getElementById('link-activities');
  const isVisible = notice.classList.contains('div-table-jobs-active');

  if(isVisible) {
    element.textContent = 'Nascondi tutte le attività';
  } else {
    element.textContent = 'Visualizza tutte le attività';
  }
}
