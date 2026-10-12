<?php

/**
 * -------------------------------------------------------------------------
 * GLPI Intranet Plugin
 * -------------------------------------------------------------------------
 *
 * @package   Plugin Intranet
 * @author    Leewan Meneses
 * @license   GPL-2.0+
 * @since     2026
 *
 * -------------------------------------------------------------------------
 */

/**
 * Install hook
 *
 * Also serves as the upgrade hook: GLPI has no plugin_<name>_update(). When the
 * version in setup.php is ahead of the installed one, the plugin is flagged
 * NOTUPDATED and the "Upgrade" button posts action=install, so this function
 * runs again (see Plugin::install()). Everything below is idempotent - the
 * CREATE TABLEs are guarded by tableExists(), addKey() repairs older tables
 * and the seed content only runs on empty tables - so future upgrades gain new
 * tables/columns with no extra code.
 *
 * @return boolean
 */
function plugin_intranet_install() {
    /** @var DBmysql $DB */
    global $DB;

    // Register the "plugin_intranet" right in glpi_profilerights.
    // Profiles able to update the GLPI configuration (super-admin) get READ +
    // UPDATE, so they can publish announcements and links right away; every
    // other profile gets the right row with no access so that admins can
    // enable it later from the Profile form.
    $migration = new Migration(PLUGIN_INTRANET_VERSION);
    $migration->addRight(PluginIntranetPortal::$rightname, READ | UPDATE, ['config' => READ | UPDATE]);

    // Notice board. The DDL is ours because Migration exposes no createTable()
    // in GLPI 10 nor in 11 - addField() only ever emits ALTER TABLE, which
    // needs the table to be there - so the DDL is inline; the else branch only
    // repairs a table that predates the current keys.
    $fresh_announcements = false;
    if (!$DB->tableExists('glpi_plugin_intranet_announcements')) {
        $fresh_announcements = true;
        $migration->addPreQuery(
            "CREATE TABLE `glpi_plugin_intranet_announcements` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `title` varchar(255) DEFAULT NULL,
                `category` varchar(50) NOT NULL DEFAULT 'notice',
                `content` text,
                `is_sticky` tinyint(1) NOT NULL DEFAULT '0',
                `is_active` tinyint(1) NOT NULL DEFAULT '1',
                `date_begin` timestamp NULL DEFAULT NULL,
                `date_end` timestamp NULL DEFAULT NULL,
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `is_active` (`is_active`),
                KEY `is_sticky` (`is_sticky`),
                KEY `date_end` (`date_end`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            PLUGIN_INTRANET_VERSION . ' create glpi_plugin_intranet_announcements'
        );
    } else {
        $migration->addKey('glpi_plugin_intranet_announcements', 'is_active');
        $migration->addKey('glpi_plugin_intranet_announcements', 'is_sticky');
        $migration->addKey('glpi_plugin_intranet_announcements', 'date_end');
    }

    // Quick links.
    $fresh_links = false;
    if (!$DB->tableExists('glpi_plugin_intranet_links')) {
        $fresh_links = true;
        $migration->addPreQuery(
            "CREATE TABLE `glpi_plugin_intranet_links` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) DEFAULT NULL,
                `url` varchar(1024) DEFAULT NULL,
                `icon` varchar(64) NOT NULL DEFAULT 'ti ti-link',
                `image` varchar(255) DEFAULT NULL,
                `sort_order` int NOT NULL DEFAULT '0',
                `is_active` tinyint(1) NOT NULL DEFAULT '1',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `is_active` (`is_active`),
                KEY `sort_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC",
            PLUGIN_INTRANET_VERSION . ' create glpi_plugin_intranet_links'
        );
    } else {
        $migration->addKey('glpi_plugin_intranet_links', 'is_active');
        $migration->addKey('glpi_plugin_intranet_links', 'sort_order');
        // 1.1.0: optional uploaded icon image per quick link.
        $migration->addField(
            'glpi_plugin_intranet_links',
            'image',
            "varchar(255) DEFAULT NULL"
        );
    }

    $migration->executeMigration();

    // Seed starter content so the portal is useful immediately after install.
    // Runs only when the table has no rows at all (never on upgrades, never
    // overwriting content the admins already manage).
    $now = date('Y-m-d H:i:s');
    if ($fresh_announcements || countElementsInTable('glpi_plugin_intranet_announcements') === 0) {
        $DB->insert('glpi_plugin_intranet_announcements', [
            'title'         => 'Welcome to the Intranet! / Bem-vindo(a) a Intranet!',
            'category'      => 'news',
            'content'       => "This is the corporate notice board. Admins can publish announcements here from Setup > Plugins > Intranet.\n\nEste é o mural de avisos corporativo. Administradores podem publicar avisos em Configuração > Plugins > Intranet.",
            'is_sticky'     => 1,
            'is_active'     => 1,
            'date_begin'    => null,
            'date_end'      => null,
            'users_id'      => 0,
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }
    if ($fresh_links || countElementsInTable('glpi_plugin_intranet_links') === 0) {
        // DBmysql::insert() takes a single row: insert the starter links one
        // by one.
        $seed_links = [
            [
                'name'          => 'New ticket / Novo chamado',
                'url'           => '/front/ticket.form.php',
                'icon'          => 'ti ti-ticket',
                'sort_order'    => 10,
                'is_active'     => 1,
                'date_creation' => $now,
                'date_mod'      => $now,
            ],
            [
                'name'          => 'Knowledge base / Base de conhecimento',
                'url'           => '/front/knowbaseitem.php',
                'icon'          => 'ti ti-book',
                'sort_order'    => 20,
                'is_active'     => 1,
                'date_creation' => $now,
                'date_mod'      => $now,
            ],
            [
                'name'          => 'GLPI Project',
                'url'           => 'https://glpi-project.org',
                'icon'          => 'ti ti-world',
                'sort_order'    => 30,
                'is_active'     => 1,
                'date_creation' => $now,
                'date_mod'      => $now,
            ],
        ];
        foreach ($seed_links as $seed_link) {
            $DB->insert('glpi_plugin_intranet_links', $seed_link);
        }
    }

    return true;
}

/**
 * Uninstall hook
 *
 * @return boolean
 */
function plugin_intranet_uninstall() {
    /** @var DBmysql $DB */
    global $DB;

    // Remove the plugin right from glpi_profilerights
    ProfileRight::deleteProfileRights([PluginIntranetPortal::$rightname]);

    // Remove config values stored through GLPI's configuration API
    \Config::deleteConfigurationValues('plugin:intranet');

    // Drop the plugin tables (announcements and links belong to the plugin;
    // removing them keeps the database clean after an uninstall).
    foreach (['glpi_plugin_intranet_announcements', 'glpi_plugin_intranet_links'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE `" . $table . "`");
        }
    }

    // Remove uploaded quick-link icon images.
    $docdir = (defined('GLPI_PLUGIN_DOC_DIR')
        ? GLPI_PLUGIN_DOC_DIR
        : ((defined('GLPI_DOC_DIR') ? GLPI_DOC_DIR : '') . '/_plugins')) . '/intranet';
    if (is_dir($docdir)) {
        foreach (glob($docdir . '/link_*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    return true;
}
