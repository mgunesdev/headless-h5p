<?php

namespace Alsay\LaravelH5P\Http\Controllers;

use Alsay\LaravelH5P\Dtos\ContentFilterCriteriaDto;
use Alsay\LaravelH5P\Http\Controllers\Swagger\ContentApiSwagger;
use Alsay\LaravelH5P\Http\Requests\ContentCreateRequest;
use Alsay\LaravelH5P\Http\Requests\ContentDeleteRequest;
use Alsay\LaravelH5P\Http\Requests\ContentListRequest;
use Alsay\LaravelH5P\Http\Requests\AdminContentReadRequest;
use Alsay\LaravelH5P\Http\Requests\ContentReadRequest;
use Alsay\LaravelH5P\Http\Requests\ContentUpdateRequest;
use Alsay\LaravelH5P\Http\Requests\LibraryStoreRequest;
use Alsay\LaravelH5P\Http\Resources\ContentIndexResource;
use Alsay\LaravelH5P\Http\Resources\ContentResource;
use Alsay\LaravelH5P\Repositories\Contracts\H5PContentRepositoryContract;
use Alsay\LaravelH5P\Services\Contracts\HeadlessH5PServiceContract;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentApiController extends BaseController implements ContentApiSwagger
{
    private HeadlessH5PServiceContract $hh5pService;
    private H5PContentRepositoryContract $contentRepository;

    public function __construct(HeadlessH5PServiceContract $hh5pService, H5PContentRepositoryContract $contentRepository)
    {
        $this->hh5pService = $hh5pService;
        $this->contentRepository = $contentRepository;
    }

    public function index(ContentListRequest $request): JsonResponse
    {
        $contentFilterDto = ContentFilterCriteriaDto::instantiateFromRequest($request);
        $columns = [
          'hh5p_contents.title',
          'hh5p_contents.id',
          'hh5p_contents.uuid',
          'hh5p_contents.library_id',
          'hh5p_contents.user_id',
          'hh5p_contents.author'
        ];
        $list = $request->get('per_page') !== null && $request->get('per_page') == 0 ?
            $this->contentRepository->unpaginatedList($contentFilterDto, $columns) :
            $this->contentRepository->list($contentFilterDto, $request->get('per_page'), $columns);

        return $this->sendResponseForResource(ContentIndexResource::collection($list));
    }

    public function update(ContentUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $contentId = $this->contentRepository->edit($id, $request->get('title'), $request->get('library'), $request->get('params'), $request->get('nonce'));
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse(['id' => $contentId, 'contentRedirectUrl' => Session::get('contentRedirectUrl')]);
    }

    public function store(ContentCreateRequest $request): JsonResponse
    {
        try {
            $contentId = $this->contentRepository->create($request->get('title'), $request->get('library'), $request->get('params'), $request->get('nonce'));
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse(['id' => $contentId, 'contentRedirectUrl' => Session::get('contentRedirectUrl')]);
    }

    public function destroy(ContentDeleteRequest $request, int $id): JsonResponse
    {
        try {
            $contentId = $this->contentRepository->delete($id);
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse(['id' => $contentId]);
    }

    public function show(AdminContentReadRequest $request, int $id): JsonResponse
    {
        try {
            $settings = $this->hh5pService->getContentSettings($id);
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse($settings);
    }

    public function frontShow(ContentReadRequest $request, string $uuid): JsonResponse
    {
        try {
            $settings = $this->hh5pService->getContentSettings($request->getH5PContent()->id);
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse($settings);
    }

    public function showConfig(ContentReadRequest $request, string $uuid): JsonResponse
    {
        try {
            $settings = $this->hh5pService->getContentApiSettings($request->getH5PContent()->id);
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse($settings);
    }

    public function upload(LibraryStoreRequest $request): RedirectResponse
    {
        try {
            $content = $this->contentRepository->upload($request->file('h5p_file'));
        } catch (Exception $error) {
            logger()->error($error->getMessage());
            return redirect()->route('h5p.editor.step_2', 'new')->with('danger', 'İçerik Yüklenirken Bir Hata Oluştu!');        }

        $contentRedirectUrl = Session::get('contentRedirectUrl');

        return redirect()->to($contentRedirectUrl)->with('success', 'İçerik Oluşturuldu');
    }

    public function download(AdminContentReadRequest $request, $id): BinaryFileResponse
    {
        $filepath = $this->contentRepository->download($id);

        return response()
            ->download($filepath, '', [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            ]);
    }

    public function deleteUnused(): JsonResponse
    {
        try {
            $ids = $this->contentRepository->deleteUnused();
        } catch (Exception $error) {
            return $this->sendError($error->getMessage(), 422);
        }

        return $this->sendResponse(['ids' => $ids]);
    }
}
