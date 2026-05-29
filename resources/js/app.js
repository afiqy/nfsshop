import './bootstrap';
import { createPopper } from '@popperjs/core';
import 'bootstrap';
import 'jquery';

// Custom JS
$(".profile-dropdown #userDropdown").on("click", function (e) {
    e.preventDefault();
    const dropdownMenu = $(this).next('.dropdown-menu')[0];
    createPopper(this, dropdownMenu, {
        placement: 'bottom-end',
    });
    $(dropdownMenu).toggleClass('show');
});

// Close dropdown when clicking outside
$(document).on('click', function (e) {
    if (!$(e.target).closest('.profile-dropdown').length) {
        $('.profile-dropdown .dropdown-menu').removeClass('show');
    }
});