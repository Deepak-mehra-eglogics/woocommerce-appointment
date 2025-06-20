<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit4dbd7d38a160cc255a1e5956bb1de58e
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInit4dbd7d38a160cc255a1e5956bb1de58e', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit4dbd7d38a160cc255a1e5956bb1de58e', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit4dbd7d38a160cc255a1e5956bb1de58e::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
