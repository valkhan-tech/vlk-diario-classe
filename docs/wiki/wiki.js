(function(){
  var side=document.getElementById('side'),b=document.getElementById('burger');
  if(b)b.onclick=function(){side.classList.toggle('open')};
  var q=document.getElementById('q');
  if(q)q.addEventListener('input',function(){
    var v=q.value.trim().toLowerCase();
    side.querySelectorAll('a').forEach(function(a){a.style.display=(!v||a.textContent.toLowerCase().indexOf(v)>-1)?'':'none'});
    side.querySelectorAll('h4').forEach(function(h){var n=h.nextElementSibling,any=false;while(n&&n.tagName==='A'){if(n.style.display!=='none')any=true;n=n.nextElementSibling}h.style.display=any?'':'none'});
  });
  var toc=document.getElementById('toc');
  if(toc){var hs=document.querySelectorAll('main h2');
    if(hs.length>1){var h='<b>Nesta página</b>';hs.forEach(function(e,i){if(!e.id)e.id='s'+i;h+='<a href="#'+e.id+'">'+e.textContent+'</a>'});toc.innerHTML=h}
    else toc.style.display='none'}
  document.querySelectorAll('a[href^="#"]').forEach(function(a){a.addEventListener('click',function(){var d=document.querySelector(a.getAttribute('href'));if(d&&d.tagName==='DETAILS')d.open=true})});
  if(location.hash){var t=document.querySelector(location.hash);if(t&&t.tagName==='DETAILS')t.open=true}
})();
