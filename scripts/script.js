function startAnimation(){
  notice = document.getElementById('div-table-jobs');
  notice.classList.toggle('div-table-jobs-active');

  element = document.getElementById('link-activities');
  label1 = 'Visualizza tutte le attività';
  label2 = 'Nascondi tutte le attività'
  if(element.textContent == label1) element.textContent = label2;
  else element.textContent = label1;
}
