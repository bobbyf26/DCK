/* DCK site: page 38 scripts. Moved out of the page content on 2026-09-28; loaded by dck-site-assets.php. */
(function(){
var g=document.querySelector('[data-gallery]');if(!g)return;
var figs=[].slice.call(g.querySelectorAll('figure'));
var prev=document.querySelector('[data-gal-prev]');
var next=document.querySelector('[data-gal-next]');
var lbl=document.querySelector('[data-gal-label]');
var per=6,page=0,pages=Math.ceil(figs.length/per);
function render(){
figs.forEach(function(f,i){var vis=i>=page*per;if(vis)vis=i<(page+1)*per;f.style.display=vis?'':'none';});
lbl.textContent='Page '+(page+1)+' of '+pages;
prev.disabled=page===0;
next.disabled=page===pages-1;
}
prev.addEventListener('click',function(){if(page>0){page--;render();}});
next.addEventListener('click',function(){if(page<pages-1){page++;render();}});
render();
})();

(function(){
var m=document.getElementById('dckx-login');if(!m)return;
var f=m.querySelector('form');
var sform=document.querySelector('[data-dck-search]');
function scrollToResults(){var rw=document.querySelector('.dck-results-wrap');if(rw)setTimeout(function(){rw.scrollIntoView({behavior:'smooth',block:'start'});},250);}
if(sform)sform.addEventListener('submit',scrollToResults);
document.addEventListener('click',function(ev){if(ev.target.closest('.dck-tile'))scrollToResults();});
function close(){m.hidden=true;document.body.style.overflow='';}
document.addEventListener('click',function(e){
if(e.target.closest('[data-login-close]')){close();return;}
if(e.target===m){close();return;}
var a=e.target.closest('a[href*="wp-login.php"]');
if(!a)return;
if(a.href.indexOf('action=')>-1)return;
e.preventDefault();
try{var u=new URL(a.href);var r=u.searchParams.get('redirect_to');if(r)f.redirect_to.value=r;}catch(err){}
m.hidden=false;document.body.style.overflow='hidden';
var first=f.querySelector('input[name="log"]');if(first)first.focus();
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){if(!m.hidden)close();}});
})();
