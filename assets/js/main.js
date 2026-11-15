document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm||'هل أنت متأكد؟'))e.preventDefault()}));
const fileInput=document.querySelector('[data-image-input]'),preview=document.querySelector('[data-image-preview]');if(fileInput&&preview)fileInput.addEventListener('change',()=>{const f=fileInput.files[0];if(f){preview.src=URL.createObjectURL(f);preview.classList.remove('d-none')}});
setTimeout(()=>document.querySelectorAll('.alert').forEach(a=>bootstrap.Alert.getOrCreateInstance(a).close()),6000);
