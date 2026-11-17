(function() {
    if (window.logoutConfirmInitialized) return;
    window.logoutConfirmInitialized = true;

    function init() {
        const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
        logoutLinks.forEach(function (link) {
            if (link.dataset.logoutConfirmBound) return;
            link.dataset.logoutConfirmBound = "true";

            link.addEventListener("click", function (event) {
                event.preventDefault();
                const logoutUrl = this.href;

                Swal.fire({
                    title: "ต้องการออกจากระบบหรือไม่?",
                    text: "คุณจะต้องเข้าสู่ระบบใหม่อีกครั้ง",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#dc2626",
                    cancelButtonColor: "#6b7280",
                    confirmButtonText: "ใช่, ออกจากระบบ",
                    cancelButtonText: "ยกเลิก",
                    reverseButtons: true,
                    background: "#ffffff",
                    // iOS in-app browsers can report a taller layout viewport than
                    // the visible area. Keep this short confirmation within it.
                    heightAuto: false,
                    customClass: {
                        // Keep this confirmation compact on iOS/iPadOS web views.
                        popup: "logout-confirm-popup rounded-2xl",
                        confirmButton: "rounded-lg px-5 py-3",
                        cancelButton: "rounded-lg px-5 py-3"
                    },
                    didOpen: function (popup) {
                        const viewportHeight = window.visualViewport
                            ? window.visualViewport.height
                            : window.innerHeight;
                        const container = popup.closest('.swal2-container');
                        popup.style.setProperty('height', 'auto', 'important');
                        popup.style.setProperty('min-height', '0', 'important');
                        popup.style.setProperty('max-height', Math.max(260, viewportHeight - 32) + 'px', 'important');
                        popup.style.setProperty('overflow-y', 'auto', 'important');
                        if (container) {
                            container.style.setProperty('height', viewportHeight + 'px', 'important');
                            container.style.setProperty('min-height', '0', 'important');
                            container.style.setProperty('padding', '16px', 'important');
                            container.style.setProperty('overflow-y', 'auto', 'important');
                        }
                        const icon = popup.querySelector('.swal2-icon');
                        if (icon) {
                            icon.style.setProperty('width', '5rem', 'important');
                            icon.style.setProperty('height', '5rem', 'important');
                            icon.style.setProperty('margin', '.25rem auto .75rem', 'important');
                        }
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = logoutUrl;
                    }
                });
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
