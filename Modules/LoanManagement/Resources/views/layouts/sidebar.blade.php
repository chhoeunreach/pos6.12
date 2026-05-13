@php
    $loanMenuCollapsed = request()->cookie('loan_menu_collapsed', '0') === '1';
@endphp

<aside class="main-sidebar loan-sidebar {{ $loanMenuCollapsed ? 'loan-menu-collapsed' : '' }}">
    <section class="sidebar">
        @include('loanmanagement::layouts.menu')
    </section>
</aside>

<style>
@media (max-width: 767px) {
    .loan-sidebar .treeview-menu {
        padding-left: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.loan-sidebar .treeview > a');
    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var parent = toggle.parentElement;
            if (window.innerWidth <= 767) {
                parent.classList.toggle('menu-open');
                parent.classList.toggle('active');
            }
        });
    });
});
</script>

