<?php

namespace Utils;

use Google_Service_Docs;
use Google_Service_Docs_BatchUpdateDocumentRequest;
use Google_Service_Docs_Document;
use Google_Service_Docs_Request;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;

class GoogleDocsApi
{
    protected $client;
    protected $config;
    protected $googleServiceDocs;
    protected $googleServiceDrive;

    public function __construct($config)
    {
        $this->config = $config;
        $this->client = (new GoogleOAuth($config))->getClient();
        $this->googleServiceDocs = new Google_Service_Docs($this->client);
        $this->googleServiceDrive = new Google_Service_Drive($this->client);
    }

    /**
     * @param $docId
     * @return Google_Service_Docs_Document
     */
    public function getDocument($docId)
    {
        return $this->googleServiceDocs->documents->get($docId);
    }

    /**
     * @param $title
     * @return Google_Service_Docs_Document
     */
    public function createNewDoc($title)
    {
        $document = new Google_Service_Docs_Document(array(
            'title' => $title
        ));

        $document = $this->googleServiceDocs->documents->create($document);

        return $document;
    }

    /**
     * @param $fileId
     * @return Google_Service_Drive_DriveFile
     */
    public function getDriveFile($fileId)
    {
        $response = $this->googleServiceDrive->files->get($fileId, array(
            "fields"=>"webViewLink"));
        return $response;
    }

    /**
     * @param $fileId
     * @return mixed
     *
     * Function provides link for download in docx format
     *
     */
    public function exportDriveFile($fileId)
    {
        $response = $this->googleServiceDrive->files->get($fileId, array(
            "fields"=>"exportLinks"));
        return $response->exportLinks['application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    }

    /**
     * @param $fileId
     * @return mixed
     */
    public function getPDF($fileId)
    {
        $response = $this->googleServiceDrive->files->export($fileId, 'application/pdf', array(
            'alt' => 'media'));
        $content = $response->getBody()->getContents();
        return $content;
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function createUserFolder($userId)
    {
        $folderMetadata = new Google_Service_Drive_DriveFile(array(
            'name' => $userId,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $this->googleServiceDrive->files->create($folderMetadata, array(
            'fields' => 'id'));
        return $folder->id;
    }

    /**
     * @return \Google_Service_Drive_FileList
     */
    public function getFoldersList()
    {
        $pageToken = null;

        return $this->googleServiceDrive->files->listFiles([
            'q' => "mimeType='application/vnd.google-apps.folder' and trashed = false",
            'spaces' => 'drive',
            'pageToken' => $pageToken,
            'fields' => 'nextPageToken, files(id, name)',
        ]);
    }

    /**
     * @param $id
     * @param $newTitle
     * @param $folderId
     * @return mixed
     */
    public function copyDocument($id, $newTitle, $folderId)
    {

        $copy = new Google_Service_Drive_DriveFile(
            [
                'name' => $newTitle,
                'parents' => [$folderId]
            ]
        );
        $driveResponse = $this->googleServiceDrive->files->copy($id, $copy);
        $documentCopyId = $driveResponse->id;

        $createPermissions = new Google_Service_Drive_Permission(
            [
                'role' => 'reader',
                'type' => 'anyone'
            ]
        );

        $this->googleServiceDrive->permissions->create($documentCopyId, $createPermissions);

        return $documentCopyId;
    }

    /**
     * @param $documentId
     * @param $values
     * @param $listItems
     * @param $elementsIndexes
     * @return \Google_Service_Docs_BatchUpdateDocumentResponse|string
     */
    public function insertValues($documentId, $values, $listItems, $elementsIndexes)
    {
        $result = '';

        $startIndexes = [];

        foreach ($elementsIndexes as $el) {
            foreach ($el as $startIndex => $insertion) {
                $startIndexes[] = $startIndex;
            }
        }
        rsort($startIndexes);

        $sortedElements = $this->sortIndexes($elementsIndexes, $startIndexes);

        $requests = array();

        $replacementsArray = [];
        foreach ($values as $value) {
            $replacementsArray[$value['variable']] = $value['replacement'];
        }

        foreach ($sortedElements as $element) {
            foreach ($element as $varName => $indexes) {
                // Delete list element
                if (in_array($varName, array_column($values, 'variable')) && empty($replacementsArray[$varName])) {
                    if (in_array($varName, $listItems)) {
                        $requests[] = new Google_Service_Docs_Request(
                            [
                                'deleteParagraphBullets' => [
                                    'range' => [
                                        'startIndex' => $indexes['startIndex'],
                                        'endIndex' => $indexes['endIndex']
                                    ]
                                ]
                            ]
                        );
                    }
                    $requests[] = new Google_Service_Docs_Request(
                        [
                            'deleteContentRange' => [
                                'range' => [
                                    'startIndex' => $indexes['startIndex'],
                                    'endIndex' => $indexes['endIndex']
                                ]
                            ],
                        ]
                    );
                }
            }
        }


        foreach ($values as $value) {
            $requests[] = new Google_Service_Docs_Request(
                [
                    'replaceAllText' => [
                        'containsText' => [
                            'text' => $value['variable'],
                            'matchCase' => true,
                        ],
                        'replaceText' => $value['replacement'],
                    ],
                ]
            );
        }

        $request = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
            'requests' => $requests
        ));

        if (!empty($requests)) {
            $result = $this->googleServiceDocs->documents->batchUpdate($documentId, $request);
        }

        return $result;
    }

    /**
     * @param $elementsArray
     * @param $indexArray
     * @return array
     */
    public function sortIndexes($elementsArray, $indexArray)
    {
        $result = [];
        foreach ($indexArray as $index) {
            foreach ($elementsArray as $variable => $element) {
                if (isset($element[$index])) {
                    $result[][$variable] = $element[$index];
                }
            }
        }

        return $result;
    }
}
