const t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t;
document.getElementById('th')?.addEventListener('click',()=>{const n=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=n;localStorage.setItem('theme',n)});
document.querySelectorAll('.stat,.card').forEach((el,i)=>{el.style.animationDelay=i*60+'ms'});
