<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticiasController extends Controller
{
    public function index()
    {
        try {
            // Obtener noticias desde tbposts (no news_posts)
            $news = DB::table('tbposts')
                ->select('id', 'title', 'description', 'image', 'likes', 'dislikes', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'title' => $post->title,
                        'description' => $post->description,
                        'image' => $post->image ? base64_encode($post->image) : null,
                        'likes' => $post->likes ?? 0,
                        'dislikes' => $post->dislikes ?? 0,
                        'created_at' => $post->created_at
                    ];
                });

            return view('Noticias', compact('news'));
        } catch (\Exception $e) {
            return view('Noticias', ['news' => [], 'error' => $e->getMessage()]);
        }
    }

    public function like(Request $request, $postId)
    {
        try {
            $userId = $request->input('user_id') ?? session('user_id');

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Verificar si el usuario ya dio like
            $existingLike = DB::table('tbnewslikes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->first();

            if ($request->method() === 'POST') {
                // Agregar like
                if (!$existingLike) {
                    // Remover dislike si existe
                    DB::table('tbnewsdislikes')
                        ->where('post_id', $postId)
                        ->where('user_id', $userId)
                        ->delete();

                    // Agregar like
                    DB::table('tbnewslikes')->insert([
                        'post_id' => $postId,
                        'user_id' => $userId,
                        'created_at' => now()
                    ]);

                    // Actualizar contador en tbposts
                    DB::table('tbposts')
                        ->where('id', $postId)
                        ->increment('likes');

                    // Decrementar dislikes si había
                    $post = DB::table('tbposts')->where('id', $postId)->first();
                    if ($post && $post->dislikes > 0) {
                        DB::table('tbposts')
                            ->where('id', $postId)
                            ->decrement('dislikes');
                    }
                }
            } else {
                // Remover like (DELETE)
                if ($existingLike) {
                    DB::table('tbnewslikes')
                        ->where('post_id', $postId)
                        ->where('user_id', $userId)
                        ->delete();

                    // Decrementar contador
                    DB::table('tbposts')
                        ->where('id', $postId)
                        ->decrement('likes');
                }
            }

            return response()->json(['message' => 'Acción completada']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dislike(Request $request, $postId)
    {
        try {
            $userId = $request->input('user_id') ?? session('user_id');

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Verificar si el usuario ya dio dislike
            $existingDislike = DB::table('tbnewsdislikes')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->first();

            if ($request->method() === 'POST') {
                // Agregar dislike
                if (!$existingDislike) {
                    // Remover like si existe
                    DB::table('tbnewslikes')
                        ->where('post_id', $postId)
                        ->where('user_id', $userId)
                        ->delete();

                    // Agregar dislike
                    DB::table('tbnewsdislikes')->insert([
                        'post_id' => $postId,
                        'user_id' => $userId,
                        'created_at' => now()
                    ]);

                    // Actualizar contador en tbposts
                    DB::table('tbposts')
                        ->where('id', $postId)
                        ->increment('dislikes');

                    // Decrementar likes si había
                    $post = DB::table('tbposts')->where('id', $postId)->first();
                    if ($post && $post->likes > 0) {
                        DB::table('tbposts')
                            ->where('id', $postId)
                            ->decrement('likes');
                    }
                }
            } else {
                // Remover dislike (DELETE)
                if ($existingDislike) {
                    DB::table('tbnewsdislikes')
                        ->where('post_id', $postId)
                        ->where('user_id', $userId)
                        ->delete();

                    // Decrementar contador
                    DB::table('tbposts')
                        ->where('id', $postId)
                        ->decrement('dislikes');
                }
            }

            return response()->json(['message' => 'Acción completada']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getComments($postId)
    {
        try {
            $comments = DB::table('tbcomments')
                ->join('tbusers', 'tbcomments.user_id', '=', 'tbusers.id')
                ->where('tbcomments.post_id', $postId)
                ->select('tbcomments.*', 'tbusers.username')
                ->orderBy('tbcomments.created_at', 'desc')
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
            $lastComment = DB::table('tbcomments')
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
            $commentsInLastHour = DB::table('tbcomments')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $hourAgo)
                ->count();

            if ($commentsInLastHour >= 10) {
                return response()->json(['error' => 'Has alcanzado el límite de 10 comentarios por hora'], 429);
            }

            // ANTI-SPAM: Verificar comentarios duplicados en los últimos 5 minutos
            $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $duplicateComment = DB::table('tbcomments')
                ->where('user_id', $userId)
                ->where('post_id', $postId)
                ->where('description', $description)
                ->where('created_at', '>=', $fiveMinutesAgo)
                ->first();

            if ($duplicateComment) {
                return response()->json(['error' => 'No puedes publicar el mismo comentario repetidamente'], 400);
            }

            // Si pasa todas las validaciones, insertar el comentario
            // SOLO usar las columnas que existen en la tabla
            DB::table('tbcomments')->insert([
                'post_id' => $postId,
                'user_id' => $userId,
                'description' => trim($description),
                'created_at' => now()
                // Remover 'updated_at' porque no existe en la tabla
            ]);

            return response()->json(['message' => 'Comentario publicado exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    public function updateComment(Request $request, $commentId)
    {
        try {
            $userId = session('user_id');
            $description = $request->input('description');

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Verificar que el comentario pertenece al usuario
            $comment = DB::table('tbcomments')
                ->where('id', $commentId)
                ->where('user_id', $userId)
                ->first();

            if (!$comment) {
                return response()->json(['error' => 'Comentario no encontrado o no autorizado'], 404);
            }

            // Actualizar comentario - solo description sin updated_at
            DB::table('tbcomments')
                ->where('id', $commentId)
                ->update([
                    'description' => trim($description)
                    // Remover 'updated_at' porque no existe en la tabla
                ]);

            return response()->json(['message' => 'Comentario actualizado']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteComment($commentId)
    {
        try {
            $userId = session('user_id');

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

            // Verificar que el comentario pertenece al usuario
            $comment = DB::table('tbcomments')
                ->where('id', $commentId)
                ->where('user_id', $userId)
                ->first();

            if (!$comment) {
                return response()->json(['error' => 'Comentario no encontrado o no autorizado'], 404);
            }

            // Eliminar comentario
            DB::table('tbcomments')->where('id', $commentId)->delete();

            return response()->json(['message' => 'Comentario eliminado']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
}