(() => {
'use strict';
const dialog = document.getElementById('reh-price-dialog');
if (!dialog) return;
const body = dialog.querySelector('[data-price-dialog-body]');
const page = document.getElementById('reh-pricing-page');
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let busy = false;
function message(text, success = false) {
    const target = dialog.open ? dialog.querySelector('.reh-price-message') : document.querySelector('.reh-price-message');
    if (!target) return;
    target.textContent = text; target.dataset.success = String(success); target.hidden = false;
}
async function request(url, options = {}) {
    const response = await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,...options.headers}});
    if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('تعذر قراءة الاستجابة؛ حدّث الصفحة وتحقق من تسجيل الدخول.');
    const data = await response.json();
    if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'تعذر إتمام الطلب.');
    return data;
}
function initialize() {
    document.querySelectorAll('[data-price-filter]').forEach(select => {
        if (window.jQuery?.fn.select2 && !window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).select2({dir:'rtl',width:'100%',placeholder:'الكل',closeOnSelect:false}).on('change',filter);
        }
    });
}
function filter() {
    if (!page) return;
    const search = (page.querySelector('[data-price-search]')?.value || '').trim().toLowerCase();
    const selections = {};
    page.querySelectorAll('[data-price-filter]').forEach(select=>{selections[select.dataset.priceFilter]=[...select.selectedOptions].map(o=>o.value);});
    let count = 0;
    page.querySelectorAll('[data-price-row]').forEach(row=>{
        const statuses=selections.status||[],categories=selections.category||[];
        const visible=row.textContent.toLowerCase().includes(search)&&(!categories.length||categories.includes(row.dataset.category))&&(!statuses.length||statuses.includes(row.dataset.status)||(statuses.includes('changed')&&row.dataset.changed==='1'));
        row.hidden=!visible;if(visible)count++;
    });
    const empty=page.querySelector('[data-price-empty]');if(empty)empty.hidden=count>0;
}
async function refresh(book) {
    if (!page) return;
    const url = new URL(page.dataset.url,location.href);if(book)url.searchParams.set('book',book);
    const data=await request(url);
    page.querySelector('#reh-price-content').innerHTML=data.html;history.replaceState(null,'',url);initialize();
}
async function open(url) {
    dialog.querySelector('.reh-price-message').hidden=true;
    body.innerHTML='<p role="status">جاري تحميل تفاصيل التسعير…</p>';
    if(!dialog.open)dialog.showModal();
    try{body.innerHTML=(await request(url)).html;}catch(error){message(error.message);}
}
document.addEventListener('click',event=>{
    const button=event.target.closest('[data-price-open],[data-price-close],[data-price-unit]');
    if(!button)return;
    if(button.hasAttribute('data-price-close')) {if(!busy)dialog.close();return;}
    event.preventDefault();open(button.dataset.priceOpen||button.dataset.priceUnit);
});
document.addEventListener('input',event=>{if(event.target.matches('[data-price-search]'))filter();});
async function changed(event){
    try {
        if(event.target.matches('[data-price-book]'))await refresh(event.target.value);
        if(event.target.matches('[data-price-filter]'))filter();
        if(event.target.matches('[data-price-unit-book]'))await open(event.target.dataset.url+'?book='+encodeURIComponent(event.target.value));
    }catch(error){message(error.message);}
}
if(window.jQuery){window.jQuery(document).on('change.rehPricing','[data-price-book],[data-price-unit-book]',changed);}
document.addEventListener('change',event=>{if(!window.jQuery||event.target.matches('[data-price-filter]'))changed(event);});
document.addEventListener('submit',async event=>{
    const form=event.target.closest('[data-price-form]');if(!form)return;
    event.preventDefault();if(busy)return;busy=true;
    const button=form.querySelector('button[type="submit"],button:not([type])');if(button)button.disabled=true;
    try {
        const payload=new FormData(form);
        const data=await request(form.action,{method:'POST',body:payload});
        if(form.hasAttribute('data-price-preview')) {body.innerHTML=data.html;dialog.querySelector('.reh-price-message').hidden=true;if(!dialog.open)dialog.showModal();}
        else if(form.hasAttribute('data-price-estimate')){await open(form.dataset.reloadUrl);message(data.message,true);}
        else{if(dialog.open)dialog.close();await refresh(data.book);message(data.message,true);}
    }catch(error){message(error.message);}
    finally{busy=false;if(button)button.disabled=false;}
});
dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
initialize();
})();

