<?php

namespace Kirby\Cms;

use Closure;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Http\Response;
use Kirby\Toolkit\Str;

/**
 * Plugin assets are automatically copied/linked
 * to the media folder, to make them publicly
 * available. This class handles the magic around that.
 *
 * @package   Kirby Cms
 * @author    Bastian Allgeier <bastian@getkirby.com>
 * @link      https://getkirby.com
 * @copyright Bastian Allgeier
 * @license   https://getkirby.com/license
 */
class PluginAssets extends Collection
{
	public static function factory(Plugin $plugin): static
	{
		// get assets defined in the plugin extension
		if ($assets = $plugin->extends()['assets'] ?? null) {
			if ($assets instanceof Closure) {
				$assets = $assets();
			}

			// normalize array: use relative path as
			// key when no key is defined
			foreach ($assets as $key => $root) {
				if (is_int($key) === true) {
					unset($assets[$key]);
					$path = Str::after($root, $plugin->root() . '/');
					$assets[$path] = $root;
				}
			}
		}

		// fallback: if no assets are defined in the plugin extension,
		// use all files in the plugin's `assets` directory
		if ($assets === null) {
			$assets = [];
			$root   = $plugin->root() . '/assets';

			foreach (Dir::index($root, true) as $path) {
				if (is_file($root . '/' . $path) === true) {
					$assets[$path] = $root . '/' . $path;
				}
			}
		}

		$collection = new static([], $plugin);

		foreach ($assets as $path => $root) {
			$collection->$path = new PluginAsset($path, $root, $plugin);
		}

		return $collection;
	}

	public function plugin(): Plugin
	{
		return $this->parent;
	}

	/**
	 * Clean old/deprecated assets on every resolve
	 */
	public static function clean(string $pluginName): void
	{
		if ($plugin = App::instance()->plugin($pluginName)) {
			$media  = $plugin->mediaRoot();
			$assets = $plugin->assets();

			// get outdated media files by comparing all
			// files in the media folder against the current set
			// of asset paths
			$files  = Dir::index($media, true);
			$files  = array_diff($files, $assets->keys());

			foreach ($files as $file) {
				$root = $media . '/' . $file;

				if (is_file($root) === true) {
					F::remove($root);
				} else {
					Dir::remove($root);
				}
			}
		}
	}

	/**
	 * Create a symlink for a plugin asset and
	 * return the public URL
	 */
	public static function resolve(
		string $pluginName,
		string $path
	): Response|null {
		if ($plugin = App::instance()->plugin($pluginName)) {
			// do some spring cleaning for older files
			static::clean($pluginName);

			if ($asset = $plugin->asset($path)) {
				// create a symlink if possible
				$asset->publish();

				// return the file response
				return Response::file($asset->root());
			}
		}

		return null;
	}
}
