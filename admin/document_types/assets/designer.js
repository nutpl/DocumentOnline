window.onload = async function(){

const pdf=await window.pdfjsLib.getDocument(window.pdfFile).promise;

const page=await pdf.getPage(1);

const viewport=page.getViewport({
    scale:1.5
});

const canvas=document.getElementById("pdfCanvas");

const ctx=canvas.getContext("2d");

canvas.width=viewport.width;

canvas.height=viewport.height;

page.render({

    canvasContext:ctx,

    viewport:viewport

});

}