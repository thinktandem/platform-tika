#!/usr/bin/php
<?php

/**
 * @file Platform-ifying script for Drupal 8.
 *
 * The purpose of this script is to reapply the Platform customizations for
 * Drupal 8, Composer-Edition.  It is based on the Drupal-Composer project.
 *
 * Because Platform.sh requires a read-only file system, the initial snapshot
 * that is used for creating a project MUST be able to run without disk
 * modification.  For that reason, we cannot simply use the Drupal-Composer
 * project directly as both it and the normal Drupal installer need to write
 * to the settings.php file.  The alternative is to check only the web directory
 * into Git, including the Platform-specific settings.php and
 * settings.platformsh.php files.
 *
 * The only files in this repository that should be directly updated are:
 *
 * - This script, if necessary, but probably not.
 * - scripts/platformsh/settings.php, which will override the default Drupal
 *   settings.php file.  Users may customize this file for their particular sites.
 * - scripts/platformsh/settings.platformsh/php, which is common to all projects
 *   and should generally not be updated. It maps Platform's environment variables
 *   to Drupal database credentials, $settings, and so forth.
 * - .platform.app.yaml and .platform/*, which provide the Platform.sh-endorsed
 *   default configuration for a Drupal 8 site.
 *
 * To update this starter kit when a new release of Drupal, or a new release of
 * the Drupal-Composer project, is available, do the following on a separate
 * branch:
 *
 * git remote add dc git@github.com:drupal-composer/drupal-project.git
 * git fetch --all
 * git merge --squash dc/8.x
 * php scripts/platformsh/Platformify.php
 * git add .
 * git commit -m "Your message here"
 *
 */

namespace PlatformSh\composer;

/**
 * The Platformification script for Drupal.
 */
class Platformify {

  /**
   * Makes the necessary changes to the repository to keep it Platform-friendly.
   */
  public static function run() {
    static::addPatches();
    static::composerUpdate();
    static::copySettingsFiles();
  }

  /**
   * Execute Composer itself.
   */
  protected static function composerUpdate() {
    shell_exec(sprintf('cd %s && composer update', escapeshellarg(static::getProjectRoot())));
  }

  /**
   * Copy the Platformified settings files into place.
   */
  protected static function copySettingsFiles() {
    copy(static::getProjectRoot() . '/scripts/platformsh/settings.php', static::getProjectRoot() . '/web/sites/default/settings.php');
    copy(static::getProjectRoot() . '/scripts/platformsh/settings.platformsh.php', static::getProjectRoot() . '/web/sites/default/settings.platformsh.php');
  }

  /**
   * Adds necessary patches to the composer.json file.
   *
   * Never apply a patch here that is not already submitted upstream on
   * Drupal.org.
   *
   * @todo Stub this out to a no-op once those patches are committed upstream
   * and included in a stable patch release.
   */
  protected static function addPatches() {
    static::updateComposerJson(function(array $composer) {
      $composer['extra']['patches']['drupal/core'] = [
        "Redirect to install.php on empty DB" => "https://www.drupal.org/files/issues/drupal-redirect_to_install-728702-92.patch",
        "Staging directory should not have to be writeable" => "https://www.drupal.org/files/issues/2466197-59.patch",
      ];
      return $composer;
    });
  }

  /**
   * Returns the path to the docroot of this project.
   *
   * @return string
   *   The path to the docroot.
   */
  protected static function getDocRoot() {
    return static::getProjectRoot() .  '/web';
  }

  /**
   * Determines the root directory of the current project.
   *
   * The root directory is defined as "where composer.json is". This function
   * is mainly to work aorund guesswork around where the script is run from vs.
   * what the "current working directory" may be as a result.
   *
   * @return string
   *   The root directyory of the current project.
   */
  protected static function getProjectRoot() {
    static $dir;

    if (empty($dir)) {
      $dir = getcwd();
      while (!file_exists($dir . '/composer.json')) {
        $dir = realpath($dir . '/..');
      }
    }

    return $dir;
  }

  /**
   * Makes specified modifications to the composer.json file.
   *
   * @param callable $updates
   *   A callable that takes an array-ified composer.json file and returns
   *   a modified version of it.
   */
  protected function updateComposerJson(callable $updates) {
    $root = static::getProjectRoot();
    $composer = json_decode(file_get_contents($root . '/composer.json'), TRUE);

    $composer = $updates($composer);

    file_put_contents($root . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
}

Platformify::run();
