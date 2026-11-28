<?php

namespace App\Http\Controllers\API\V1;

use App\Services\Support\FileGatewayService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use OpenApi\Attributes as OA;

/**
 * متحكم تدفق الملفات المؤسسية (Enterprise File Streaming Controller).
 *
 * مصمم وفق نمط المتحكم النحيف (Skinny Controller):
 * - يستقبل الطلبات ويمررها مباشرة إلى FileGatewayService.
 * - يعالج استثناءات HttpException الناتجة من طبقة الخدمة ويعيد استجابات موحدة.
 * - خالٍ تماماً من الاستعلامات المباشرة أو منطق الأعمال والتحقق الأمني.
 */
class FileController extends BaseController
{
    public function __construct(
        protected FileGatewayService $fileGatewayService
    ) {}

    /**
     * عرض وتدفق الوسائط العامة المصرح بها (Public Media Stream).
     */
    #[OA\Get(
        path: '/api/v1/files/signed/{path}',
        summary: 'show',
        tags: ['Files'],
        operationId: 'show_FileController',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function show(string $path): Response
    {
        try {
            return $this->fileGatewayService->streamPublicMedia($path);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), [], $e->getStatusCode());
        }
    }

    /**
     * عرض وتدفق الملفات المحمية عبر الروابط المؤقتة الموقعة رقمياً (Cryptographically Signed Ephemeral Stream).
     */
    #[OA\Get(
        path: '/api/v1/files/signed/{token}',
        summary: 'showSigned',
        tags: ['Files'],
        operationId: 'showSigned_FileController',
        parameters: [new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function showSigned(Request $request, string $path): Response
    {
        try {
            return $this->fileGatewayService->streamSignedFile($request, $path);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), [], $e->getStatusCode());
        }
    }
}
