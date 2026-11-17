(function ($) {
    'use strict';

    let activeRequest = null;
    let debounceTimer = null;

    function resultSelector($form) {
        return String($form.data('search-results') || '').trim();
    }

    function buildUrl($form) {
        const action = $form.attr('action') || window.location.pathname;
        const query = $form.serializeArray().filter((field) => field.name !== 'page');
        const encoded = $.param(query);
        return action + (encoded ? (action.includes('?') ? '&' : '?') + encoded : '');
    }

    function replaceResults($form, url, pushHistory) {
        const selector = resultSelector($form);
        if (!selector) return;

        if (activeRequest) activeRequest.abort();
        const $current = $(selector).first();
        $current.attr('aria-busy', 'true').addClass('opacity-60');

        activeRequest = $.ajax({
            url: url,
            method: 'GET',
            dataType: 'html',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (html) {
            const $page = $('<div>').append($.parseHTML(html, document, true));
            const $next = $page.find(selector).first();
            if (!$next.length) return;
            $next.attr('data-live-replaced', 'true');
            $current.replaceWith($next);
            if (pushHistory) window.history.replaceState({}, '', url);
            $(document).trigger('jquery-live-search:updated', [selector]);
        }).fail(function (_xhr, status) {
            if (status !== 'abort') window.location.assign(url);
        }).always(function () {
            activeRequest = null;
            $(selector).first().removeAttr('aria-busy').removeClass('opacity-60');
        });
    }

    $(document).on('submit.jqueryLiveSearch', 'form[data-jquery-search-form]', function (event) {
        event.preventDefault();
        const $form = $(this);
        replaceResults($form, buildUrl($form), true);
    });

    $(document).on('input.jqueryLiveSearch', 'form[data-jquery-search-form] input[name="search"]', function () {
        const $form = $(this).closest('form');
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(function () {
            replaceResults($form, buildUrl($form), true);
        }, 300);
    });

    $(document).on('change.jqueryLiveSearch', 'form[data-jquery-search-form] select', function () {
        const $form = $(this).closest('form');
        replaceResults($form, buildUrl($form), true);
    });

    $(document).on('click.jqueryLiveSearch', '[data-jquery-search-form] [data-search-reset]', function (event) {
        event.preventDefault();
        const $form = $(this).closest('form');
        $form[0].reset();
        replaceResults($form, buildUrl($form), true);
    });

    $(document).on('click.jqueryLiveSearch', '[data-jquery-search-result] a[href*="page="]', function (event) {
        const $results = $(this).closest('[data-jquery-search-result]');
        const formSelector = String($results.data('search-form') || '').trim();
        const $form = formSelector ? $(formSelector).first() : $('form[data-jquery-search-form]').first();
        if (!$form.length) return;
        event.preventDefault();
        replaceResults($form, this.href, true);
    });

    $(document).on('click.jqueryLiveSearch', '[data-live-replaced] .deleteBtn', function (event) {
        event.preventDefault();
        const id = $(this).data('id');
        const remove = function () { window.location.assign('delete.php?id=' + encodeURIComponent(id)); };
        if (window.Swal) {
            Swal.fire({ icon:'warning', title:'ยืนยันการลบ', text:'เมื่อลบแล้วจะไม่สามารถกู้คืนข้อมูลได้', showCancelButton:true, confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก', confirmButtonColor:'#dc2626' }).then(function(result){ if(result.isConfirmed) remove(); });
        } else if (window.confirm('ยืนยันการลบข้อมูลนี้?')) remove();
    });

    $(document).on('submit.jqueryLiveSearch', '[data-live-replaced] .template-delete-form', function (event) {
        event.preventDefault();
        const form = this;
        const remove = function () { HTMLFormElement.prototype.submit.call(form); };
        if (window.Swal) {
            Swal.fire({ icon:'warning', title:'ยืนยันการลบแม่แบบ', text:'ข้อมูลแม่แบบและตำแหน่งช่องกรอกจะถูกลบ', showCancelButton:true, confirmButtonText:'ลบ', cancelButtonText:'ยกเลิก', confirmButtonColor:'#dc2626' }).then(function(result){ if(result.isConfirmed) remove(); });
        } else if (window.confirm('ยืนยันการลบแม่แบบนี้?')) remove();
    });

    $(document).on('click.jqueryLiveSearch', '[data-live-replaced] .openTeacherModalBtn', function () {
        const data=this.dataset, modal=document.getElementById('teacherViewModal'), overlay=document.getElementById('teacherViewOverlay');
        if(!modal||!overlay)return;
        modal.querySelector('.tv-code').textContent=data.code||'-'; modal.querySelector('.tv-name').textContent=data.name||'-'; modal.querySelector('.tv-prefix').textContent=data.prefix||'-'; modal.querySelector('.tv-email').textContent=data.email||'-'; modal.querySelector('.tv-department').textContent=data.department||'-'; modal.querySelector('.tv-position').textContent=data.position||'-'; modal.querySelector('.tv-gcount').textContent=data.groupsCount||'0'; modal.querySelector('.tv-groups').textContent=data.groups||'-'; modal.querySelector('.tv-status').textContent=data.status||'-';
        overlay.classList.remove('hidden'); modal.classList.remove('hidden');
    });

    $(document).on('click.jqueryLiveSearch', '[data-live-replaced] .openStaffModalBtn', function () {
        const data=this.dataset, modal=document.getElementById('staffViewModal'), overlay=document.getElementById('staffViewOverlay');
        if(!modal||!overlay)return;
        modal.querySelector('.sv-code').textContent=data.code||'-'; modal.querySelector('.sv-name').textContent=data.name||'-'; modal.querySelector('.sv-prefix').textContent=data.prefix||'-'; modal.querySelector('.sv-email').textContent=data.email||'-'; modal.querySelector('.sv-username').textContent=data.username||'-'; modal.querySelector('.sv-department').textContent=data.department||'-'; modal.querySelector('.sv-status').textContent=data.status||'-';
        overlay.classList.remove('hidden'); modal.classList.remove('hidden'); modal.classList.add('flex');
    });

    $(document).on('click.jqueryLiveSearch', '[data-live-replaced] .openViewModalBtn', function () {
        const data=this.dataset, modal=document.getElementById('studentViewModal'), overlay=document.getElementById('studentViewOverlay');
        if(!modal||!overlay)return;
        modal.querySelector('.mv-student-code').textContent=data.studentCode||'-'; modal.querySelector('.mv-name').textContent=(data.firstname||'')+' '+(data.lastname||''); modal.querySelector('.mv-email').textContent=data.email||'-'; modal.querySelector('.mv-faculty').textContent=data.faculty||'-'; modal.querySelector('.mv-department').textContent=data.department||'-'; modal.querySelector('.mv-group').textContent=data.group||'-'; modal.querySelector('.mv-prefix').textContent=data.prefix||'-'; modal.querySelector('.mv-advisor').textContent=data.advisor||'-'; modal.querySelector('.mv-status').textContent=data.status||'-';
        overlay.classList.remove('hidden'); modal.classList.remove('hidden');
    });
})(window.jQuery);
