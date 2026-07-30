<?php

declare(strict_types=1);

namespace Auth2\Keycloak\Auth;

use stdClass;

/**
 * WordPressSessionSync
 *
 * Creates or updates a WordPress user from the claims contained in a validated
 * Keycloak JWT and logs that user in, establishing a native WordPress session.
 *
 * This bridges the Keycloak identity with the WordPress user model so that
 * existing WordPress plugins / themes continue working without changes.
 */
class WordPressSessionSync
{
    /**
     * Ensure a WordPress user exists for the given Keycloak claims, then set
     * the current WordPress authentication cookies so the request is treated as
     * authenticated for the rest of the WP bootstrap.
     *
     * @param  stdClass $claims  Decoded JWT payload returned by KeycloakTokenValidator.
     * @return \WP_User          The resolved (or newly created) WordPress user.
     * @throws \RuntimeException When WordPress user creation fails.
     */
    public function sync(stdClass $claims): \WP_User
    {
        $email    = $claims->email ?? '';
        $username = $claims->preferred_username ?? $email;

        if ($email === '') {
            throw new \RuntimeException('Keycloak token is missing the email claim.');
        }

        $wpUser = get_user_by('email', $email);

        if ($wpUser === false) {
            $userId = wp_create_user($username, wp_generate_password(), $email);

            if (is_wp_error($userId)) {
                throw new \RuntimeException(
                    'Could not create WordPress user: ' . $userId->get_error_message()
                );
            }

            $wpUser = get_user_by('id', $userId);
        }

        // Keep the WP display name in sync with Keycloak
        $displayName = trim(($claims->given_name ?? '') . ' ' . ($claims->family_name ?? ''));
        if ($displayName !== '' && $wpUser->display_name !== $displayName) {
            wp_update_user([
                'ID'           => $wpUser->ID,
                'display_name' => $displayName,
                'first_name'   => $claims->given_name ?? $wpUser->first_name,
                'last_name'    => $claims->family_name ?? $wpUser->last_name,
            ]);
        }

        // Synchronise Keycloak realm roles to WordPress roles
        $this->syncRoles($wpUser, $claims);

        // Log the user in for this WordPress request
        wp_set_current_user($wpUser->ID);
        wp_set_auth_cookie($wpUser->ID, false);

        return $wpUser;
    }

    /**
     * Map Keycloak realm roles to WordPress roles.
     *
     * Only roles that exist in WordPress are applied; unknown Keycloak roles
     * are silently ignored so that Keycloak-specific roles do not break the WP
     * role system.
     *
     * @param  \WP_User $user    WordPress user to update.
     * @param  stdClass $claims  Decoded JWT payload.
     */
    private function syncRoles(\WP_User $user, stdClass $claims): void
    {
        /** @var string[] $keycloakRoles */
        $keycloakRoles = $claims->roles ?? [];

        $wpRoleNames = array_keys(wp_roles()->roles);

        foreach ($keycloakRoles as $role) {
            if (in_array($role, $wpRoleNames, true) && !in_array($role, $user->roles, true)) {
                $user->add_role($role);
            }
        }
    }
}
