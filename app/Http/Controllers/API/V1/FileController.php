<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Support\FileGatewayService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    public function showSigned(Request $request, string $path): Response
    {
        try {
            return $this->fileGatewayService->streamSignedFile($request, $path);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), [], $e->getStatusCode());
        }
    }
}
