<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticiasController extends Controller
{
    public function index()
    {
        try {
            // Obtener noticias de la base de datos local
            $news = DB::table('news_posts')
                ->select('id', 'title', 'description', 'image', 'likes', 'dislikes', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'description' => $item->description,
                        'image' => $item->image,
                        'likes' => $item->likes ?? 0,
                        'dislikes' => $item->dislikes ?? 0,
                        'created_at' => $item->created_at
                    ];
                })->toArray();

            return view('Noticias', compact('news'));
        } catch (\Exception $e) {
            // Si hay error, mostrar vista con array vacío
            $news = [];
            return view('Noticias', compact('news'));
        }
    }

    public function like(Request $request, $postId)
    {
        try {
            $userId = $request->input('user_id') ?? session('user_id');
            
            // Verificar si ya existe el like
            $existingLike = DB::table('post_likes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->first();

            if ($existingLike) {
                // Eliminar like
                DB::table('post_likes')
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->delete();
                
                // Decrementar contador
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->decrement('likes');
            } else {
                // Eliminar dislike si existe
                DB::table('post_dislikes')
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->delete();
                
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->decrement('dislikes');

                // Agregar like
                DB::table('post_likes')->insert([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => now()
                ]);
                
                // Incrementar contador
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->increment('likes');
            }

            return response()->json(['message' => 'Success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dislike(Request $request, $postId)
    {
        try {
            $userId = $request->input('user_id') ?? session('user_id');
            
            // Verificar si ya existe el dislike
            $existingDislike = DB::table('post_dislikes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->first();

            if ($existingDislike) {
                // Eliminar dislike
                DB::table('post_dislikes')
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->delete();
                
                // Decrementar contador
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->decrement('dislikes');
            } else {
                // Eliminar like si existe
                DB::table('post_likes')
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->delete();
                
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->decrement('likes');

                // Agregar dislike
                DB::table('post_dislikes')->insert([
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => now()
                ]);
                
                // Incrementar contador
                DB::table('news_posts')
                    ->where('id', $postId)
                    ->increment('dislikes');
            }

            return response()->json(['message' => 'Success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getComments($postId)
    {
        try {
            $comments = DB::table('post_comments')
                ->join('tbusers', 'post_comments.user_id', '=', 'tbusers.id') // Cambiar usuarios por tbusers
                ->where('post_comments.post_id', $postId)
                ->select('post_comments.*', 'tbusers.username')
                ->orderBy('post_comments.created_at', 'desc')
                ->get()
                ->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'description' => $comment->description,
                        'created_at' => $comment->created_at,
                        'user' => [
                            'id' => $comment->user_id,
                            'username' => $comment->username
                        ]
                    ];
                });

            return response()->json($comments);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function postComment(Request $request, $postId)
    {
        try {
            $userId = $request->input('user_id') ?? session('user_id');
            $description = $request->input('description');

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Validar contenido del comentario
            if (empty(trim($description)) || strlen($description) < 3) {
                return response()->json(['error' => 'El comentario debe tener al menos 3 caracteres'], 400);
            }

            if (strlen($description) > 500) {
                return response()->json(['error' => 'El comentario no puede exceder 500 caracteres'], 400);
            }

            // ANTI-SPAM: Verificar último comentario del usuario (cooldown de 30 segundos)
            $lastComment = DB::table('post_comments')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastComment) {
                $lastCommentTime = new \DateTime($lastComment->created_at);
                $now = new \DateTime();
                $timeDiff = $now->getTimestamp() - $lastCommentTime->getTimestamp();
                
                if ($timeDiff < 30) { // 30 segundos de cooldown
                    $remainingTime = 30 - $timeDiff;
                    return response()->json(['error' => "Espera {$remainingTime} segundos antes de comentar nuevamente"], 429);
                }
            }

            // ANTI-SPAM: Verificar límite de comentarios por hora (máximo 10)
            $hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
            $commentsInLastHour = DB::table('post_comments')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $hourAgo)
                ->count();

            if ($commentsInLastHour >= 10) {
                return response()->json(['error' => 'Has alcanzado el límite de 10 comentarios por hora'], 429);
            }

            // ANTI-SPAM: Verificar comentarios duplicados en los últimos 5 minutos
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $duplicateComment = DB::table('post_comments')
                ->where('user_id', $userId)
                ->where('post_id', $postId)
                ->where('description', $description)
                ->where('created_at', '>=', $fiveMinutesAgo)
                ->first();

            if ($duplicateComment) {
                return response()->json(['error' => 'No puedes publicar el mismo comentario repetidamente'], 400);
            }

            // ANTI-SPAM: Verificar comentarios muy similares (básico)
            $similarComment = DB::table('post_comments')
                ->where('user_id', $userId)
                ->where('post_id', $postId)
                ->where('created_at', '>=', $fiveMinutesAgo)
                ->get();

            foreach ($similarComment as $comment) {
                $similarity = $this->calculateSimilarity($description, $comment->description);
                if ($similarity > 0.8) { // 80% de similitud
                    return response()->json(['error' => 'No puedes publicar comentarios muy similares'], 400);
                }
            }

            // Si pasa todas las validaciones, insertar el comentario
            DB::table('post_comments')->insert([
                'post_id' => $postId,
                'user_id' => $userId,
                'description' => trim($description),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'Comentario publicado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    // Función auxiliar para calcular similitud entre textos
    private function calculateSimilarity($text1, $text2)
    {
        $text1 = strtolower(trim($text1));
        $text2 = strtolower(trim($text2));
        
        if ($text1 === $text2) {
            return 1.0;
        }
        
        // Calcular similitud básica usando similar_text
        $percent = 0;
        similar_text($text1, $text2, $percent);
        return $percent / 100;
    }

    public function updateComment(Request $request, $commentId)
    {
        try {
            $description = $request->input('description');

            DB::table('post_comments')
                ->where('id', $commentId)
                ->update([
                    'description' => $description,
                    'updated_at' => now()
                ]);

            return response()->json(['message' => 'Comentario actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteComment($commentId)
    {
        try {
            DB::table('post_comments')
                ->where('id', $commentId)
                ->delete();

            return response()->json(['message' => 'Comentario eliminado']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}