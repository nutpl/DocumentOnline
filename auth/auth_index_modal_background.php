<div class="auth-index-modal__background" aria-hidden="true">
    <iframe src="/Project/index.php" title=""></iframe>
</div>
<script>
(() => {
    const background = document.currentScript.previousElementSibling;
    const frame = background?.querySelector('iframe');
    frame?.addEventListener('load', () => background.classList.add('is-loaded'), { once: true });
})();
</script>
