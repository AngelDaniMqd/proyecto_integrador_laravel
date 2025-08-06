<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NoticiasController extends Controller
{
    public function index()
    {
        try {
            // Usar error_log para debug en Docker (más confiable que Laravel Log)
            error_log('=== DEBUG NoticiasController INICIO ===');
            
            // Verificar conexión DB primero
            try {
                $pdo = DB::connection()->getPdo();
                error_log('✅ Conexión BD exitosa');
            } catch (\Exception $e) {
                error_log('❌ Error conexión BD: ' . $e->getMessage());
                return view('noticias', ['news' => []]); // minúscula para Docker
            }
            
            // Verificar si la tabla existe
            if (!DB::getSchemaBuilder()->hasTable('tbposts')) {
                error_log('❌ Tabla tbposts no existe');
                return view('noticias', ['news' => []]);
            }
            
            error_log('✅ Tabla tbposts existe');

            // Obtener datos de forma más simple
            $rawPosts = DB::table('tbposts')
                ->select('id', 'title', 'description', 'image', 'likes', 'dislikes', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            error_log('✅ Registros obtenidos: ' . $rawPosts->count());

            // Transformar con manejo de errores robusto
            $news = [];
            foreach ($rawPosts as $post) {
                try {
                    error_log('Procesando post ID: ' . $post->id);
                    
                    // Manejar imagen de forma segura
                    $imageBase64 = null;
                    if ($post->image && !empty($post->image)) {
                        try {
                            if (strlen($post->image) > 100) { // Verificar que sea una imagen válida
                                $imageBase64 = 'data:image/png;base64,' . base64_encode($post->image);
                                error_log('✅ Imagen procesada para post ' . $post->id);
                            }
                        } catch (\Exception $e) {
                            error_log('❌ Error procesando imagen post ' . $post->id . ': ' . $e->getMessage());
                        }
                    }

                    $news[] = [
                        'id' => $post->id,
                        'title' => $post->title ?? 'Sin título',
                        'description' => $post->description ?? 'Sin descripción',
                        'image' => $imageBase64,
                        'likes' => (int)($post->likes ?? 0),
                        'dislikes' => (int)($post->dislikes ?? 0),
                        'created_at' => $post->created_at
                    ];
                    
                } catch (\Exception $e) {
                    error_log('❌ Error procesando post ' . $post->id . ': ' . $e->getMessage());
                    // Agregar post sin imagen si hay error
                    $news[] = [
                        'id' => $post->id,
                        'title' => $post->title ?? 'Sin título',
                        'description' => $post->description ?? 'Sin descripción',
                        'image' => null,
                        'likes' => (int)($post->likes ?? 0),
                        'dislikes' => (int)($post->dislikes ?? 0),
                        'created_at' => $post->created_at
                    ];
                }
            }

            error_log('✅ Transformación completada. Total: ' . count($news));
            error_log('=== Enviando a vista noticias (minúscula) ===');

            return view('noticias', compact('news')); // minúscula para Docker
            
        } catch (\Exception $e) {
            error_log('❌ ERROR GENERAL en NoticiasController: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Mostrar error en producción para debug
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Error interno del servidor'
            ], 500);
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