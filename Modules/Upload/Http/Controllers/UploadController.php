<?php

namespace Modules\Upload\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Modules\Upload\Entities\Upload;

class UploadController extends Controller
{

    public function filepondUpload(Request $request) {
        $request->validate([
            'image' => 'required|image|mimes:png,jpeg,jpg'
        ]);

        if ($request->hasFile('image')) {
            $uploaded_file = $request->file('image');
            $filename = now()->timestamp . '.' . $uploaded_file->getClientOriginalExtension();
            $folder = uniqid() . '-' . now()->timestamp;

            $file = Image::make($uploaded_file)->encode($uploaded_file->getClientOriginalExtension());

            Storage::put('temp/' . $folder . '/' . $filename, $file);

            Upload::create([
                'folder'   => $folder,
                'filename' => $filename
            ]);

            return $folder;
        }

        return false;
    }


    public function filepondDelete(Request $request) {
        $upload = Upload::where('folder', $request->getContent())->first();

        Storage::deleteDirectory('temp/' . $upload->folder);
        $upload->delete();

        return response(null);
    }


    public function dropzoneUpload(Request $request) {
        $request->validate([
            'file' => 'required|file|image|mimes:png,jpeg,jpg,gif,webp|max:5120'
        ]);

        $file = $request->file('file');

        $filename = now()->timestamp . '.' . strtolower($file->getClientOriginalExtension());

        Storage::putFileAs('temp/dropzone/', $file, $filename);

        return response()->json([
            'name'          => $filename,
            'original_name' => $file->getClientOriginalName(),
        ]);
    }

    public function dropzoneDelete(Request $request) {
        // basename() strips any directory components to prevent path traversal (e.g. ../../.env)
        $fileName = basename((string) $request->file_name);

        Storage::delete('temp/dropzone/' . $fileName);

        return response()->json($fileName, 200);
    }
}
