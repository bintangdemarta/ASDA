<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\User;
use App\Enums\AiConversationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiChatController extends Controller
{
    private array $faqKnowledgeBase;

    public function __construct()
    {
        // Initialize FAQ knowledge base for rule-based responses
        $this->faqKnowledgeBase = [
            'insurance' => [
                'kapan asuransi aktif' => 'Asuransi aktif sejak tanggal mulai yang tercantum dalam polis Anda.',
                'bagaimana cara klaim' => 'Untuk melakukan klaim, Anda bisa mengirimkan permintaan klaim melalui dashboard dan melengkapi dokumen yang diperlukan.',
                'berapa besar pertanggungan' => 'Besar pertanggungan tercantum dalam detail polis Anda. Silakan cek di dashboard polis.',
                'jenis asuransi apa saja' => 'ASABRI menyediakan berbagai jenis asuransi seperti asuransi kesehatan, jiwa, dan kendaraan.',
            ],
            'claim' => [
                'status klaim' => 'Anda bisa mengecek status klaim Anda di dashboard klaim Anda.',
                'lama proses klaim' => 'Lama proses klaim biasanya 7-14 hari kerja tergantung kompleksitas klaim.',
                'dokumen apa saja' => 'Dokumen yang diperlukan tergantung jenis klaim, biasanya KTP, polis, dan bukti kerugian.',
            ],
            'tni' => [
                'manfaat asuransi bagi tni' => 'Asuransi memberikan perlindungan finansial bagi TNI dan keluarga dalam berbagai risiko.',
                'asabri untuk tni' => 'ASABRI adalah asuransi yang dirancang khusus untuk anggota TNI dan keluarganya.',
            ]
        ];
    }

    /**
     * Handle user message and generate AI response
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        $userMessage = strtolower(trim($request->message));

        // Classify intent and get response
        $intent = $this->classifyIntent($userMessage);
        $aiResponse = $this->getResponse($userMessage, $intent);

        // Determine if escalation to admin is needed
        $shouldEscalate = $this->shouldEscalate($userMessage, $aiResponse, $intent);

        // Create conversation record
        $conversation = $user->aiConversations()->create([
            'user_input' => $request->message,
            'ai_response' => $aiResponse,
            'intent' => $intent,
            'status' => $shouldEscalate ? AiConversationStatus::ESCALATED : AiConversationStatus::COMPLETED,
            'context' => [
                'previous_intent' => $intent,
                'escalated' => $shouldEscalate,
            ],
        ]);

        if ($shouldEscalate) {
            // In a real implementation, this would assign to an available admin
            $conversation->update([
                'escalated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'user_input' => $request->message,
                'ai_response' => $aiResponse,
                'intent' => $intent,
                'status' => $shouldEscalate ? 'escalated' : 'completed',
                'needs_admin_attention' => $shouldEscalate,
            ],
        ]);
    }

    /**
     * Get conversation history
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = $user->aiConversations()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * Classify the intent of the user message
     *
     * @param string $message
     * @return string|null
     */
    private function classifyIntent(string $message): ?string
    {
        // Check for insurance-related keywords
        if (preg_match('/(asuransi|polis|pertanggungan|coverage|perlindungan)/i', $message)) {
            return 'insurance';
        }

        // Check for claim-related keywords
        if (preg_match('/(klaim|claim|pengajuan|status|pencairan|disburse)/i', $message)) {
            return 'claim';
        }

        // Check for TNI-related keywords
        if (preg_match('/(tni|militer|pamong praja|angkatan|perwira)/i', $message)) {
            return 'tni';
        }

        // Check for general help
        if (preg_match('/(bantuan|help|bantu|tanya|tanya|pertanyaan)/i', $message)) {
            return 'help';
        }

        // Check for admin escalation keywords
        if (preg_match('/(admin|bantuan manual|pegawai|petugas|manusia)/i', $message)) {
            return 'escalation';
        }

        return null;
    }

    /**
     * Get response for the user message based on intent
     *
     * @param string $message
     * @param string|null $intent
     * @return string
     */
    private function getResponse(string $message, ?string $intent): string
    {
        if ($intent === null) {
            return $this->getFallbackResponse($message);
        }

        // Check if it's a simple FAQ question
        if (isset($this->faqKnowledgeBase[$intent])) {
            foreach ($this->faqKnowledgeBase[$intent] as $question => $answer) {
                if (str_contains($message, $question)) {
                    return $answer;
                }
            }

            // If no exact match found in FAQ, try to find closest match
            $closestAnswer = $this->getClosestAnswer($message, $this->faqKnowledgeBase[$intent]);
            if ($closestAnswer) {
                return $closestAnswer;
            }
        }

        // Use external AI API if not found in FAQ
        return $this->getExternalAIResponse($message, $intent);
    }

    /**
     * Get closest answer from FAQ based on similarity
     *
     * @param string $message
     * @param array $faqSection
     * @return string|null
     */
    private function getClosestAnswer(string $message, array $faqSection): ?string
    {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($faqSection as $question => $answer) {
            $score = $this->calculateSimilarity($message, $question);
            if ($score > $bestScore && $score > 0.3) { // Threshold for similarity
                $bestScore = $score;
                $bestMatch = $answer;
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);

        // Simple word overlap similarity
        $words1 = explode(' ', $str1);
        $words2 = explode(' ', $str2);

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    /**
     * Get fallback response when no match is found
     *
     * @param string $message
     * @return string
     */
    private function getFallbackResponse(string $message): string
    {
        if (preg_match('/(hai|halo|hello|siang|pagi|malam)/i', $message)) {
            return 'Halo! Saya asisten digital ASABRI. Bagaimana saya bisa membantu Anda hari ini?';
        }

        if (preg_match('/(terima kasih|makasih|thank)/i', $message)) {
            return 'Sama-sama! Jika Anda memiliki pertanyaan lebih lanjut, jangan ragu untuk bertanya.';
        }

        return 'Terima kasih atas pertanyaan Anda. Saat ini saya masih belajar, dan akan menyampaikan pertanyaan ini kepada admin untuk mendapatkan jawaban yang lebih tepat.';
    }

    /**
     * Get response from external AI API
     *
     * @param string $message
     * @param string $intent
     * @return string
     */
    private function getExternalAIResponse(string $message, string $intent): string
    {
        // In a real implementation, this would call an external AI API like OpenAI
        // For this demo, we'll return a mock response based on intent

        $responses = [
            'insurance' => 'Berdasarkan informasi yang saya miliki, Anda ingin mengetahui tentang asuransi. Asuransi ASABRI memberikan perlindungan bagi anggota TNI dan keluarganya. Untuk informasi lebih lanjut, Anda bisa melihat detail polis di dashboard.',
            'claim' => 'Untuk informasi klaim, Anda bisa mengecek status permohonan klaim di dashboard. Proses klaim biasanya memakan waktu 7-14 hari kerja tergantung kompleksitas permohonan.',
            'tni' => 'Sebagai anggota TNI, Anda memiliki akses ke berbagai manfaat asuransi dari ASABRI. Ini termasuk perlindungan kesehatan, jiwa, dan asuransi kendaraan.',
            'help' => 'Saya adalah asisten digital ASABRI yang siap membantu Anda. Anda bisa bertanya tentang: status klaim, detail polis, prosedur klaim, manfaat asuransi, atau meminta bantuan langsung dari petugas.',
            'escalation' => 'Permintaan Anda sedang kami alihkan ke petugas admin untuk bantuan lebih lanjut. Mohon bersabar, petugas akan segera menghubungi Anda.',
        ];

        return $responses[$intent] ?? $this->getFallbackResponse($message);
    }

    /**
     * Determine if conversation should be escalated to admin
     *
     * @param string $userMessage
     * @param string $aiResponse
     * @param string|null $intent
     * @return bool
     */
    private function shouldEscalate(string $userMessage, string $aiResponse, ?string $intent): bool
    {
        // Escalate if user explicitly requests admin
        if (preg_match('/(admin|pegawai|petugas|manusia|bantuan manual)/i', $userMessage)) {
            return true;
        }

        // Escalate if AI response indicates uncertainty
        if (str_contains($aiResponse, 'masih belajar') || str_contains($aiResponse, 'tidak yakin')) {
            return true;
        }

        // Escalate complex financial/claims questions that require human verification
        if ($intent === 'claim' && preg_match('/(kompleks|rumit|pencairan besar|kendala|sulit)/i', $userMessage)) {
            return true;
        }

        return false;
    }
}
