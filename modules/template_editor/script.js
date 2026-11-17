const canvas=document.getElementById("canvas");

canvas.onclick=function(e){

    if(e.target.id==="paper"){

        createField(e.offsetX,e.offsetY);

    }

}

function createField(x,y){

    const div=document.createElement("div");

    div.className="field";

    div.style.left=x+"px";

    div.style.top=y+"px";

    div.style.width="180px";

    div.style.height="35px";

    div.contentEditable=true;

    div.innerHTML="field";

    canvas.appendChild(div);

}