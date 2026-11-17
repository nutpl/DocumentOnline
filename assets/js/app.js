// Mobile Menu

const menuBtn=document.getElementById("menu-btn");

const mobileMenu=document.getElementById("mobile-menu");

if(menuBtn){

menuBtn.onclick=function(){

mobileMenu.classList.toggle("hidden");

}

}

// Navbar Shadow

window.addEventListener("scroll",function(){

const nav=document.querySelector("nav");

if(window.scrollY>50){

nav.classList.add("nav-shadow");

}else{

nav.classList.remove("nav-shadow");

}

});

// Fade Up Animation

const observer=new IntersectionObserver(entries=>{

entries.forEach(entry=>{

if(entry.isIntersecting){

entry.target.classList.add("show");

}

});

});

document.querySelectorAll(".fade-up").forEach(el=>{

observer.observe(el);

});

// Back To Top

const topBtn=document.getElementById("backToTop");

window.addEventListener("scroll",()=>{

if(window.scrollY>400){

topBtn.classList.remove("hidden");

}else{

topBtn.classList.add("hidden");

}

});

if(topBtn){

topBtn.onclick=()=>{

window.scrollTo({

top:0,

behavior:"smooth"

});

};

}

// Counter

const counters=document.querySelectorAll(".counter");

counters.forEach(counter=>{

counter.innerText='0';

const update=()=>{

const target=+counter.getAttribute("data-target");

const current=+counter.innerText;

const increment=target/100;

if(current<target){

counter.innerText=Math.ceil(current+increment);

setTimeout(update,20);

}else{

counter.innerText=target;

}

}

update();

});