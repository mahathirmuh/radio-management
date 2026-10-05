<?php

namespace App\Helpers;

use App\Models\FileAttachment;
use App\Models\Log\LogActivity;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use GuzzleHttp\Client;

class FileApps
{
    //FUNC File Attachment
    public static function insertFile($fileRequest, $s3_ParamObject, $title)
    {
        try {
            $s3_Bucket = 'PMS/'; //Default
            $s3_Object = base64_encode(Auth::user()->company->id) . '/' . $s3_ParamObject;
            $file['ext'] = $fileRequest->getClientOriginalExtension(); //Ext File
            /*exFile
             $title parameter example format( '_photoprofile', '_fileIjazah', '_fileKtp', .etc)
             */
            $file['title'] = $title . "." . $file['ext']; //Name File in Storage
            //Save file to Server (Rancangan Pemanggilan Path)
            $path = $s3_Bucket . $s3_Object;
            $post = Storage::disk('public')->putFileAs($path, $fileRequest, $file['title']);
            if ($post) {
                return [
                    'success' => true,
                    's3_bucket' => $s3_Bucket,
                    's3_object' =>  $s3_Object . '/' . $file['title'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "File Gagal disimpan!"
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' =>  $e->getMessage(),
            ];
        }
    }

    public static function insertFileLocal($fileRequest, $s3_ParamObject, $title)
    {
        try {
            $s3_Bucket = 'PMS/'; //Default
            $s3_Object = base64_encode(Auth::user()->company->id) . '/' . $s3_ParamObject;
            $file['ext'] = $fileRequest->getClientOriginalExtension(); //Ext File
            /*
             $title parameter example format( '_photoprofile', '_fileIjazah', '_fileKtp', .etc)
             */
            $file['title'] = $title . "." . strtolower($file['ext']); //Name File in Storage
            //Save file to Server (Rancangan Pemanggilan Path)
            $path = $s3_Bucket . $s3_Object;
            $post = Storage::disk('public')->putFileAs($path, $fileRequest, $file['title']);
            if ($post) {
                return [
                    'success' => true,
                    's3_bucket' => $s3_Bucket,
                    's3_object' =>  $s3_Object . '/' . $file['title'],
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "File Gagal disimpan!"
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' =>  $e->getMessage(),
            ];
        }
    }

    public static function deleteFileLocal($s3_Bucket, $s3_Object)
    {
        try {
            $path = $s3_Bucket . $s3_Object;
            $delete = Storage::disk('public')->delete($path);
            if ($delete) {
                // Check if the directory is empty and delete it
                $directory = dirname($path);
                $files = Storage::disk('public')->files($directory);
                if (empty($files)) {
                    Storage::disk('public')->deleteDirectory($directory);
                }
                return [
                    'success' => true,
                    'message' => "File Berhasil dihapus!"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "File Gagal dihapus!"
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' =>  $e->getMessage(),
            ];
        }
    }
}
