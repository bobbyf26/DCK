/* DCK site: page 44 scripts. Moved out of the page content on 2026-09-28; loaded by dck-site-assets.php. */
(function(){
var m=document.getElementById('dckx-login');if(!m)return;
var f=m.querySelector('form');
function close(){m.hidden=true;document.body.style.overflow='';}
document.addEventListener('click',function(e){
if(e.target.closest('[data-login-close]')){close();return;}
if(e.target===m){close();return;}
var a=e.target.closest('a[href*="wp-login.php"]');
if(!a||a.href.indexOf('action=')>-1)return;
e.preventDefault();
try{var u=new URL(a.href);var r=u.searchParams.get('redirect_to');if(r)f.redirect_to.value=r;}catch(err){}
m.hidden=false;document.body.style.overflow='hidden';
var first=f.querySelector('input[name="log"]');if(first)first.focus();
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!m.hidden)close();});
})();
