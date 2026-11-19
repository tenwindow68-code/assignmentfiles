
<?php
namespace assignsubmission_externalstorage\local;

defined('MOODLE_INTERNAL') || die();

use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class azure_blob_storage {

    /**
     * Return Azure Blob client.
     *
     * @return BlobRestProxy
     */
    public static function get_client(): BlobRestProxy {
        global $CFG;

        require_once(__DIR__ . '/../../vendor/autoload.php');

        $accounturl = trim((string)get_config(
            'assignsubmission_externalstorage',
            'azure_account_url'
        ));

        $sastoken = trim((string)get_config(
            'assignsubmission_externalstorage',
            'azure_sas_token'
        ));

        if (empty($accounturl) || empty($sastoken)) {
            throw new \moodle_exception(
                'Azure Blob Storage configuration is missing.'
            );
        }

        // Remove a leading question mark if present.
        $sastoken = ltrim($sastoken, '?');

        // Build SDK connection string using the SAS credential.
        $connectionstring =
            'BlobEndpoint=' . rtrim($accounturl, '/') . ';' .
            'SharedAccessSignature=' . $sastoken;

        return BlobRestProxy::createBlobService($connectionstring);
    }

    /**
     * Get configured container.
     *
     * @return string
     */
    public static function get_container(): string {
        $container = trim((string)get_config(
            'assignsubmission_externalstorage',
            'azure_container'
        ));

        if (empty($container)) {
            throw new \moodle_exception(
                'Azure container is not configured.'
            );
        }

        return $container;
    }

    /**
     * Upload a file to Azure.
     *
     * @param string $localpath Temporary uploaded file path.
     * @param string $blobname Azure blob name.
     * @return void
     */
    public static function upload(string $localpath, string $blobname): void {

        if (!is_readable($localpath)) {
            throw new \moodle_exception('Uploaded file is not readable.');
        }

        $client = self::get_client();
        $container = self::get_container();

        $stream = fopen($localpath, 'rb');

        if ($stream === false) {
            throw new \moodle_exception('Unable to open uploaded file.');
        }

        try {
            $client->createBlockBlob(
                $container,
                $blobname,
                $stream
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * Download a blob.
     *
     * @param string $blobname Azure blob name.
     * @return resource
     */
    public static function download(string $blobname) {
        $client = self::get_client();

        $result = $client->getBlob(
            self::get_container(),
            $blobname
        );

        return $result->getContentStream();
    }

    /**
     * Delete a blob.
     *
     * @param string $blobname Azure blob name.
     * @return void
     */
    public static function delete(string $blobname): void {
        $client = self::get_client();

        $client->deleteBlob(
            self::get_container(),
            $blobname
        );
    }
}
