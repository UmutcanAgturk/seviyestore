<?php

declare(strict_types=1);

namespace Seviye\Security\Http\Admin;

/**
 * Registers the top-level "Seviye Kullanıcılar" wp-admin menu and its four
 * submenus, delegating each page's actual rendering to its own class. A
 * standalone top-level menu (not nested under WordPress' native
 * "Kullanıcılar") on purpose: Genel Merkez staff have no native
 * `list_users`/`edit_users` capability, so they cannot even see WP's own
 * Kullanıcılar parent menu - nesting under it would hide this entirely
 * from the audience it is mainly built for. See {@see AdminAccess}.
 */
final class SeviyeUsersMenu
{
    public function __construct(
        private readonly UserListPage $userListPage,
        private readonly UserAuthorizationAdminPage $authorizationPage,
        private readonly StudentDirectoryPage $studentDirectoryPage
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);

        $this->userListPage->registerActions();
        $this->authorizationPage->registerActions();
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Seviye Kullanıcılar', 'seviye-security'),
            __('Seviye Kullanıcılar', 'seviye-security'),
            AdminAccess::capability(),
            UserListPage::SLUG_ALL,
            [$this->userListPage, 'renderAll'],
            'dashicons-groups',
            3
        );

        add_submenu_page(
            UserListPage::SLUG_ALL,
            __('Seviye Kullanıcılar', 'seviye-security'),
            __('Seviye Kullanıcılar', 'seviye-security'),
            AdminAccess::capability(),
            UserListPage::SLUG_ALL,
            [$this->userListPage, 'renderAll']
        );

        add_submenu_page(
            UserListPage::SLUG_ALL,
            __('Seviye Yetkilendirme', 'seviye-security'),
            __('Seviye Yetkilendirme', 'seviye-security'),
            AdminAccess::capability(),
            UserAuthorizationAdminPage::MENU_SLUG,
            [$this->authorizationPage, 'render']
        );

        add_submenu_page(
            UserListPage::SLUG_ALL,
            __('Veli', 'seviye-security'),
            __('Veli', 'seviye-security'),
            AdminAccess::capability(),
            UserListPage::SLUG_VELI,
            [$this->userListPage, 'renderVeli']
        );

        add_submenu_page(
            UserListPage::SLUG_ALL,
            __('Öğrenci', 'seviye-security'),
            __('Öğrenci', 'seviye-security'),
            AdminAccess::capability(),
            StudentDirectoryPage::SLUG,
            [$this->studentDirectoryPage, 'render']
        );
    }
}
