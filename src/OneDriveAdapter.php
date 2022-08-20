<?php

namespace TomSim\FlysystemOneDrive;

use ArrayObject;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Microsoft\Graph\Model\DriveItem;

class OneDriveAdapter implements FilesystemAdapter
{
    //use NotSupportingVisibilityTrait;

    /**
     *  @var \Microsoft\Graph\Graph
     */
    private Graph $graph;

    /**
     * @var \League\Flysystem\PathPrefixer
     */
    private PathPrefixer $prefixer;

    /**
     * OneDriveAdapter constructor.
     *
     * @param \Microsoft\Graph\Graph $graph
     * @param string                 $prefix
     * @param string                 $base
     */
    public function __construct(Graph $graph, $prefix = 'root', $base = '/drive/')
    {
        $this->graph = $graph;
        $this->prefixer = new PathPrefixer($base.$prefix.':');
    }

    /**
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function fileExists(string $path): bool
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            $response = $this->graph->createRequest('GET', $path)->execute();
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);
            if ($responseMessage?->error?->code == 'itemNotFound') {
                return false;
            }

            throw UnableToCheckExistence::forLocation(str_replace(':', '', $path));
        }

        return true;
    }

    /**
     * @throws FilesystemException
     * @throws UnableToCheckDirectoryExistence
     */
    public function directoryExists(string $path): bool
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            $response = $this->graph->createRequest('GET', $path)->execute();
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);
            if ($responseMessage?->error?->code == 'itemNotFound') {
                return false;
            }

            throw UnableToCheckDirectoryExistence::forLocation(str_replace(':', '', $path));
        }

        return true;
    }

    /**
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $this->upload($path, $contents);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path);
        }
    }

    /**
     * @param resource $contents
     *
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->upload($path, $contents);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path);
        }
    }

    /**
     * @throws UnableToReadFile
     * @throws FilesystemException
     */
    public function read(string $path): string
    {
        $result = $this->readStream($path);
        if ($result->getSize() > 0) {
            return $result->getContents();
        }

        throw UnableToReadFile::fromLocation($this->prefixer->prefixPath($path), 'Empty content.');
    }

    /**
     * @throws UnableToReadFile
     * @throws FilesystemException
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            return $this->graph->createRequest('GET', $path.':/content')
                        ->setReturnType(Stream::class)
                        ->execute();
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToReadFile::fromLocation(str_replace(':', '', $this->prefixer->prefixPath($path)), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete(string $path): void
    {
        $path = $this->prefixer->prefixPath($path);

        try {
            $this->graph->createRequest('DELETE', $path)->execute();
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToDeleteFile::atLocation(str_replace(':', '', $path), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->delete($path);
        } catch (ClientException $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToDeleteDirectory::atLocation(str_replace(':', '', $this->prefixer->prefixPath($path)), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $patch = explode('/', $this->prefixer->prefixPath($path));
            $sliced = implode('/', array_slice($patch, 0, -1));

            $endpoint = $sliced.':/children';
            $endpoint = str_replace('::', '', $endpoint);

            $this->graph->createRequest('POST', $endpoint)
                ->attachBody([
                    'name'   => end($patch),
                    'folder' => new ArrayObject(),
                ])->execute();
        } catch (ClientException $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToCreateDirectory::atLocation(str_replace(':', '', $sliced).'/'.end($patch), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): FileAttributes
    {
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $info = $this->graph->createRequest('GET', $this->prefixer->prefixPath($path))
                        ->setReturnType(DriveItem::class)
                        ->execute();
            $prop = (object) $info->getProperties();

            return new FileAttributes($path, null, null, null, $prop?->file['mimeType']);
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToRetrieveMetadata::mimeType($this->prefixer->prefixPath($path), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $info = $this->graph->createRequest('GET', $this->prefixer->prefixPath($path))
                        ->setReturnType(DriveItem::class)
                        ->execute();

            return new FileAttributes($path, null, null, $info?->getlastModifiedDateTime()?->getTimestamp());
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToRetrieveMetadata::lastModified($this->prefixer->prefixPath($path), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $info = $this->graph->createRequest('GET', $this->prefixer->prefixPath($path))
                        ->setReturnType(DriveItem::class)
                        ->execute();

            return new FileAttributes($path, $info?->getSize());
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToRetrieveMetadata::fileSize($this->prefixer->prefixPath($path), $responseMessage?->error?->message);
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function webUrl(string $path): ?string
    {
        try {
            $info = $this->graph->createRequest('GET', $this->prefixer->prefixPath($path))
                        ->setReturnType(DriveItem::class)
                        ->execute();

            return $info?->getWebUrl();
        } catch (\Exception $e) {
            $responseMessage = json_decode($e?->getResponse()?->getBody(), false);

            throw UnableToRetrieveMetadata::create($this->prefixer->prefixPath($path), 'weburl', $responseMessage?->error?->message);
        }
    }

    /**
     * @throws FilesystemException
     *
     * @return iterable<StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        if (!$this->directoryExists($path)) {
            throw new Exception('Directory '.$path.' does not exist');
        }

        $items = [];

        try {
            $info = $this->graph->createRequest('GET', $this->prefixer->prefixPath($path).':/children')
                        ->execute();
            $values = $info->getBody()['value'];

            foreach ($values as $value) {
                if (!empty($value['folder'])) {
                    $items[] = new DirectoryAttributes(
                        $value['name'],
                        null,
                        strtotime($value['lastModifiedDateTime']),
                    );
                } else {
                    $items[] = new FileAttributes(
                        $value['name'],
                        $value['size'],
                        null,
                        strtotime($value['lastModifiedDateTime']),
                        $value['file']['mimeType'],
                    );
                }
            }
        } catch (\Exception $e) {
            throw new Exception('Can not list directory '.$path.' content');
        }

        return yield from $items;
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        if ($this->fileExists($destination)) {
            throw new Exception('Destination '.$destination.' exist');
        }

        $source = $this->prefixer->prefixPath($source);
        $destination = $this->prefixer->prefixPath($destination);
        $folder = implode('/', array_slice(explode('/', $destination), 0, -1)).'/';
        $forlderId = null;

        try {
            $info = $this->graph->createRequest('GET', $folder)
                        ->setReturnType(DriveItem::class)
                        ->execute();
            $forlderId = $info?->getId();
        } catch (\Exception $e) {
            // Try to create directory
            $this->createDirectory($this->prefixer->stripDirectoryPrefix($folder), $config);

            $info = $this->graph->createRequest('GET', $folder)
                        ->setReturnType(DriveItem::class)
                        ->execute();

            $forlderId = $info?->getId();
        }

        if (empty($forlderId)) {
            throw new Exception('Cannot get '.$folder.' Id');
        }

        try {
            $this->graph->createRequest('PATCH', $source)
                ->attachBody([
                    'name'            => basename($destination),
                    'parentReference' => [
                        'id' => $forlderId,
                    ],
                ])
                ->execute();
        } catch (\Exception $e) {
            throw UnableToMoveFile::fromLocationTo($sourcePath, $destinationPath);
        }
    }

    /**
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        if ($this->fileExists($destination)) {
            throw new Exception('Destination '.$destination.' exist');
        }

        $source = $this->prefixer->prefixPath($source);
        $destination = $this->prefixer->prefixPath($destination);
        $folder = implode('/', array_slice(explode('/', $destination), 0, -1)).'/';
        $forlderId = null;
        $driveId = null;

        try {
            $info = $this->graph->createRequest('GET', $folder)
                        ->setReturnType(DriveItem::class)
                        ->execute();

            $forlderId = $info?->getId();
            $driveId = $info?->getParentReference()?->getDriveId();
        } catch (\Exception $e) {
            // Try to create directory
            $this->createDirectory($this->prefixer->stripDirectoryPrefix($folder), $config);

            $info = $this->graph->createRequest('GET', $folder)
                        ->setReturnType(DriveItem::class)
                        ->execute();

            $forlderId = $info?->getId();
            $driveId = $info?->getParentReference()?->getDriveId();
        }

        try {
            $this->graph->createRequest('POST', $source.':/copy')
                ->attachBody([
                    'name'            => basename($destination),
                    'parentReference' => [
                        'driveId' => $driveId,
                        'id'      => $forlderId,
                    ],
                ])
                ->execute();
        } catch (\Exception $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * @param string          $path
     * @param resource|string $contents
     */
    protected function upload(string $path, $contents): void
    {
        $filename = basename($path);
        $path = $this->prefixer->prefixPath($path);

        try {
            $stream = Utils::streamFor($contents);
            $fileSize = $stream->getSize();

            // If upload size bigger than 4MB
            if ($fileSize > 4 * 1000 * 1000) {
                echo "#1\n";
                $uploadSession = $this->graph->createRequest('POST', $path.':/createUploadSession')
                    ->addHeaders(['Content-Type' => 'application/json'])
                    ->attachBody([
                        'item' => [
                            '@microsoft.graph.conflictBehavior' => 'rename',
                            'name'                              => $filename,
                        ],
                    ])
                    ->setReturnType(Model\UploadSession::class)
                    ->execute();

                $start = 0;
                $chunkSize = 10 * 1024 * 1024;  //10MiB
                do {
                    // Upload in chunks
                    $streamPart = new LimitStream($stream, $chunkSize, $start);
                    $end = $streamPart->getSize();
                    echo 'end:'.$end."\n";
                    $response = $this->graph->createRequest('PUT', $uploadSession->getUploadUrl())
                        ->addHeaders([
                            'Content-Length' => $end,
                            'Content-Range'  => 'bytes '.$start.'-'.($start + $end - 1).'/'.$fileSize,
                        ])
                        ->setReturnType(Model\UploadSession::class)
                        ->attachBody($streamPart)
                        ->execute();

                    $start += $chunkSize;
                } while ($end === $chunkSize);

            // TODO: verify if all chunks uploaded
                // https://docs.microsoft.com/en-us/graph/api/driveitem-createuploadsession?view=graph-rest-1.0

            // Else upload size less than 4M
            } else {
                $this->graph->createRequest('PUT', $path.':/content')
                    ->attachBody($stream)
                    ->execute();
            }
        } catch (\Exception $e) {
            throw new Exception('Upload error');
        }
    }
}
