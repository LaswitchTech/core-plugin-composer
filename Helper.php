<?php

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Helper;

class ComposerHelper extends Helper {

    // Properties
    private $Log;
    private $Path;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Call Parent Constructor
        parent::__construct();

        // Import Global Variables
        global $LOG;

        // Set Properties
        $this->Log = $LOG;

        // Add the backup log file
        $this->Log->add('composer');

        // Set the path to the Composer executable
        $this->Path = $this->Config->root() . DIRECTORY_SEPARATOR . '.composer';

        // Set the user home directory
        putenv('HOME=' . $this->Path);
        putenv('COMPOSER_HOME=' . $this->Path);

        // Create the home directory if it doesn't exist
        if (!is_dir($this->Path)) {
            mkdir($this->Path, 0755, true);
        }

        // Check if the auth.json file exists
        $authFile = $this->Path . DIRECTORY_SEPARATOR . 'auth.json';
        if(!is_file($authFile)){

            // Create the auth.json file with default content
            $defaultContent = json_encode($this->Config->get('installer','composer')['auth'] ?? [], JSON_PRETTY_PRINT);
            file_put_contents($authFile, $defaultContent);
        }
    }

    /**
     * Download the latest Composer installer
     *
     * @return bool
     */
    public function download(): bool
    {
        // Set logger
        $this->Log->set('composer');

        try {
            $this->Log->info('Downloading Composer installer...');

            // Set composer installer path
            $installerPath = $this->Path . DIRECTORY_SEPARATOR . 'composer-setup.php';

            // Create the tmp directory if it doesn't exist
            if (!is_dir(dirname($installerPath))) {
                mkdir(dirname($installerPath), 0755, true);
            }

            // Check if the installer already exists
            if (is_file($installerPath)) {
                unlink($installerPath);
            }

            // Retrieve the composer installer
            $composer = file_get_contents('https://getcomposer.org/installer');

            // Check if the composer installer was downloaded successfully
            if ($composer === false) {
                $this->Log->error('Composer failed to download.');
                return false;
            }

            // Save the installer to the tmp directory
            file_put_contents($installerPath, $composer);

            // Check if the installer was downloaded successfully
            if (!is_file($installerPath)) {
                $this->Log->error('Composer failed to save installer.');
                return false;
            }

            // Verify the installer's signature (optional but recommended)
            $signature = file_get_contents('https://composer.github.io/installer.sig');
            if ($signature === false) {
                $this->Log->error('Composer installer signature download failed.');
                return false;
            }

            // Verify the installer signature
            if (!hash_equals(hash_file('sha384', $installerPath), trim($signature))) {
                $this->Log->error('Composer installer signature verification failed.');
                return false;
            }

            $this->Log->info('Installing Composer...');

            // Run the Composer installer
            chdir(dirname($installerPath));
            $command = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php "' . $installerPath . '" --install-dir="' . $this->Path . '" --filename=composer.phar';
            $this->Log->debug('Running command: ' . $command);
            exec($command, $output, $exitCode);
            if(is_array($output)){
                $output = implode("\n", $output);
            }
            $this->Log->debug('Command output: ' . PHP_EOL . $output);

            // Check if the installation was successful
            if ($exitCode !== 0) {
                $this->Log->error('Composer installer failed (exit code ' . $exitCode . ') to run:' . implode("\n", $output));
                return false;
            }

            // Check if the composer.phar was moved successfully
            if (!is_file($this->Path . DIRECTORY_SEPARATOR . 'composer.phar')) {
                $this->Log->error('Composer installer failed to move composer.phar.');
                return false;
            }

            // Create a symlink to the Composer executable
            $composerPath = $this->Path . DIRECTORY_SEPARATOR . 'composer.phar';
            $symlinkPath = $this->Config->root() . DIRECTORY_SEPARATOR . 'composer';
            if(file_exists($symlinkPath)) {
                unlink($symlinkPath);
            }
            symlink($composerPath, $symlinkPath);

            return true;
        } catch (\Exception $e) {
            $this->Log->error('Execution failed:' . $e->getMessage());
            return false;
        }
    }

    /**
     * Install Composer dependencies
     *
     * @return bool
     */
    public function install(): bool
    {
        // Set logger
        $this->Log->set('composer');

        try {
            // Change directory to where composer.phar is located
            chdir(dirname($this->Config->root() . DIRECTORY_SEPARATOR . 'composer'));

            // Install dependencies using Composer
            $command = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php ' . escapeshellarg(basename($this->Config->root() . DIRECTORY_SEPARATOR . 'composer')) . ' install --no-dev --prefer-dist --no-interaction';
            $this->Log->debug('Running command: ' . $command);
            exec($command, $output, $exitCode);
            if(is_array($output)){
                $output = implode("\n", $output);
            }
            $this->Log->debug('Command output: ' . PHP_EOL . $output);

            // Check if the installation was successful
            if ($exitCode !== 0) {
                $this->Log->error('Composer install failed.');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->Log->error('Execution failed:' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update Composer dependencies
     *
     * @return bool
     */
    public function update(): bool
    {
        // Set logger
        $this->Log->set('composer');

        try {
            // Change directory to where composer.phar is located
            chdir(dirname($this->Config->root() . DIRECTORY_SEPARATOR . 'composer'));

            // Install dependencies using Composer
            $command = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php ' . escapeshellarg(basename($this->Config->root() . DIRECTORY_SEPARATOR . 'composer')) . ' update --no-interaction';
            $this->Log->debug('Running command: ' . $command);
            exec($command, $output, $exitCode);
            if(is_array($output)){
                $output = implode("\n", $output);
            }
            $this->Log->debug('Command output: ' . PHP_EOL . $output);

            // Check if the installation was successful
            if ($exitCode !== 0) {
                $this->Log->error('Composer update failed.');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->Log->error('Execution failed:' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up the Composer installer and phar file
     *
     * @return bool
     */
    public function clean(): bool
    {
        // Set paths
        $setup = $this->Path . DIRECTORY_SEPARATOR . 'composer-setup.php';
        $phar = $this->Path . 'composer.phar';

        // Remove the files
        if(is_file($setup)){
            unlink($setup);
        }
        if(is_file($phar)){
            unlink($phar);
        }

        // Check if the files are removed
        return (!is_file($setup) && !is_file($phar));
    }
}
