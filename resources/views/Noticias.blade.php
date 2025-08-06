<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias - Sustainity</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    @vite('resources/css/noticias.css')
    <!-- Cargar Font Awesome para los iconos -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>
  <header class="navbar">
    <div class="navbar-left">
      <a href="{{ route('rutaInicio') }}">
        <img src="{{ asset('img/DevPlay logo.png') }}" alt="Logo" class="logo-image">
      </a>
    </div>
    <nav class="navbar-center nav-links">
      <a href="{{ route('rutaInicio') }}">Inicio</a>
      <a href="/donar">Donativos</a>
      <a href="{{ route('rutaNosotros') }}">Nosotros</a>
    </nav>
    <div class="navbar-right auth-buttons">
      @if (session('logged_in'))
        <div class="user-info">
          <span class="username" onclick="toggleLogoutDropdown()">{{ session('username') }}</span>
          <div id="logoutDropdown" class="logout-dropdown">
            <button onclick="window.location.href='{{ route('rutaLogout') }}'">Cerrar sesión</button>
          </div>
        </div>
      @else
        <button class="login-btn" onclick="window.location.href='{{ route('rutaLogin') }}'">Iniciar Sesión</button>
      @endif
      <button class="news-btn" onclick="window.location.href='{{ route('rutaNoticias') }}'">Noticias</button>
    </div>
  </header>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <!-- Modal y overlay para comentarios -->
  <div id="modalOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000;"></div>
  <div id="commentsModal">
    <div class="modal-header">
      <h2>Comentarios</h2>
      <button id="modalCloseBtn">×</button>
    </div>
    
    <div class="modal-content">
      <div id="modalCommentsList"></div>
      
      <!-- Contenedor para el textarea -->
      <div class="modal-textarea-container">
        <textarea 
          id="modalNewComment" 
          placeholder="Escribe tu comentario (máximo 500 caracteres)" 
          maxlength="500"
          rows="3"></textarea>
        <div id="charCounter" style="font-size: 0.6em; color: #ffcb05; text-align: right; margin-top: 5px;">0/500</div>
      </div>
      
      <button id="modalPostCommentBtn">Publicar comentario</button>
    </div>
  </div>

  <div class="news-container">
    @if(empty($news))
      <div class="main-card">
        <div class="text-content">
          <h1>No hay noticias disponibles</h1>
          <p>Actualmente no hay noticias para mostrar.</p>
        </div>
      </div>
    @else
      @foreach($news as $newsItem)
        <div class="main-card appear-on-load" data-post-id="{{ $newsItem['id'] }}">
          <div class="text-content">
            <h1 class="news-title">{{ $newsItem['title'] }}</h1>
            <p class="news-description">{{ $newsItem['description'] }}</p>
          </div>
          @if(isset($newsItem['image']) && $newsItem['image'])
            <img class="news-image" src="data:image/jpeg;base64,{{ $newsItem['image'] }}" alt="Imagen de la noticia">
          @endif
          <div class="news-actions">
            <button class="like-btn" data-post-id="{{ $newsItem['id'] }}">
              <i class="fas fa-thumbs-up"></i> <span class="like-count">{{ $newsItem['likes'] ?? 0 }}</span>
            </button>
            <button class="dislike-btn" data-post-id="{{ $newsItem['id'] }}">
              <i class="fas fa-thumbs-down"></i> <span class="dislike-count">{{ $newsItem['dislikes'] ?? 0 }}</span>
            </button>
            <button class="toggle-comments-btn">
              <i class="fas fa-comment-dots"></i> Ver comentarios
            </button>
          </div>
        </div>
      @endforeach
    @endif
  </div>

  <footer class="footer">
    <div class="footer-content">
      <a href="#">Política de Privacidad</a> | 
      <a href="#">Términos y Condiciones</a> | 
      <a href="#">Contacto</a>
    </div>
    <p>&copy; 2024 Sustainity. Todos los derechos reservados.</p>
  </footer>

  <!-- Modal de carga -->
  <div id="loadingModal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #fff; padding: 20px; border-radius: 5px; text-align: center; font-family: 'Press Start 2P', cursive;">
      <p class="loading-text">Cargando...</p>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const baseUrl = "{{ url('/') }}";
      const userId = "{{ session('user_id') ?? 'null' }}";
      
      console.log("Base URL:", baseUrl);
      console.log("User ID:", userId);

      if (userId === "null") {
        console.warn("El usuario no está logueado; user_id es null.");
      }

      // Función para dropdown de logout
      window.toggleLogoutDropdown = function() {
        const dropdown = document.getElementById('logoutDropdown');
        if (dropdown) {
          dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        }
      }

      // Cerrar dropdown al hacer click fuera
      document.addEventListener('click', function(e) {
        const userInfo = e.target.closest('.user-info');
        const commentOptions = e.target.closest('.comment-options');
        
        // Cerrar dropdown de usuario si no se hace click en él
        if (!userInfo) {
          const dropdown = document.getElementById('logoutDropdown');
          if (dropdown) dropdown.style.display = 'none';
        }
        
        // Cerrar dropdowns de comentarios si no se hace click en ellos
        if (!commentOptions) {
          document.querySelectorAll('.comment-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
          });
        }
      });

      // Funciones para mostrar y ocultar el modal de carga
      function showLoading() {
        const modal = document.getElementById("loadingModal");
        if (modal) modal.style.display = "flex";
      }
      
      function hideLoading() {
        const modal = document.getElementById("loadingModal");
        if (modal) modal.style.display = "none";
      }

      // --- Likes / Dislikes ---
      async function sendRequest(method, url, bodyData) {
        try {
          const response = await fetch(url, {
            method: method,
            headers: { 
              "Content-Type": "application/x-www-form-urlencoded",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: bodyData
          });
          return await response.json();
        } catch (error) {
          console.error("Error en request:", error);
          throw error;
        }
      }

      async function handleLike(button) {
        const postId = button.getAttribute("data-post-id");
        if (!postId) return;
        const bodyData = `user_id=${userId}`;
        const dislikeButton = document.querySelector(`.dislike-btn[data-post-id="${postId}"]`);

        showLoading();
        try {
          if (button.classList.contains("active")) {
            button.classList.remove("active");
            let likeCountElem = button.querySelector(".like-count");
            likeCountElem.textContent = parseInt(likeCountElem.textContent) - 1;
            await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/like`, bodyData);
            console.log("Like eliminado");
          } else {
            if (dislikeButton && dislikeButton.classList.contains("active")) {
              dislikeButton.classList.remove("active");
              let dislikeCountElem = dislikeButton.querySelector(".dislike-count");
              dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) - 1;
              await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
              console.log("Dislike eliminado para dar like");
            }
            button.classList.add("active");
            let likeCountElem = button.querySelector(".like-count");
            likeCountElem.textContent = parseInt(likeCountElem.textContent) + 1;
            await sendRequest("POST", `${baseUrl}/news/posts/${postId}/like`, bodyData);
            console.log("Like registrado");
          }
        } catch (error) {
          console.error("Error en handleLike:", error);
        } finally {
          hideLoading();
        }
      }

      async function handleDislike(button) {
        const postId = button.getAttribute("data-post-id");
        if (!postId) return;
        const bodyData = `user_id=${userId}`;
        const likeButton = document.querySelector(`.like-btn[data-post-id="${postId}"]`);

        showLoading();
        try {
          if (button.classList.contains("active")) {
            button.classList.remove("active");
            let dislikeCountElem = button.querySelector(".dislike-count");
            dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) - 1;
            await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
            console.log("Dislike eliminado");
          } else {
            if (likeButton && likeButton.classList.contains("active")) {
              likeButton.classList.remove("active");
              let likeCountElem = likeButton.querySelector(".like-count");
              likeCountElem.textContent = parseInt(likeCountElem.textContent) - 1;
              await sendRequest("DELETE", `${baseUrl}/news/posts/${postId}/like`, bodyData);
              console.log("Like eliminado para dar dislike");
            }
            button.classList.add("active");
            let dislikeCountElem = button.querySelector(".dislike-count");
            dislikeCountElem.textContent = parseInt(dislikeCountElem.textContent) + 1;
            await sendRequest("POST", `${baseUrl}/news/posts/${postId}/dislike`, bodyData);
            console.log("Dislike registrado");
          }
        } catch (error) {
          console.error("Error en handleDislike:", error);
        } finally {
          hideLoading();
        }
      }

      // Event listeners para likes y dislikes
      document.querySelectorAll(".like-btn").forEach(button => {
        button.addEventListener("click", () => {
          if (userId === "null") {
            alert("Debes estar logueado para dar like.");
            return;
          }
          handleLike(button);
        });
      });
      
      document.querySelectorAll(".dislike-btn").forEach(button => {
        button.addEventListener("click", () => {
          if (userId === "null") {
            alert("Debes estar logueado para dar dislike.");
            return;
          }
          handleDislike(button);
        });
      });

      // --- Modal de Comentarios ---
      function openCommentsModal(postId) {
        const modal = document.getElementById("commentsModal");
        const overlay = document.getElementById("modalOverlay");
        if (modal && overlay) {
          modal.dataset.postId = postId;
          modal.style.display = "block";
          overlay.style.display = "block";
          loadComments(postId);
        }
      }

      function closeCommentsModal() {
        const modal = document.getElementById("commentsModal");
        const overlay = document.getElementById("modalOverlay");
        if (modal && overlay) {
          modal.style.display = "none";
          overlay.style.display = "none";
        }
      }

      async function loadComments(postId) {
        try {
          const response = await fetch(`${baseUrl}/news/posts/${postId}/comments`);
          const comments = await response.json();
          const list = document.getElementById("modalCommentsList");
          if (list) {
            list.innerHTML = "";
            comments.forEach(comment => {
              const commentDiv = document.createElement("div");
              commentDiv.classList.add("comment-item");
              commentDiv.dataset.commentId = comment.id;

              const createdAt = new Date(comment.created_at);
              const formattedDate = createdAt.toLocaleDateString('es-ES', { 
                day: '2-digit', month: 'short', year: 'numeric' 
              }) + ', ' +
              createdAt.toLocaleTimeString('es-ES', { 
                hour: '2-digit', minute: '2-digit', hour12: false, hourCycle: 'h23', timeZone: 'UTC' 
              });
              
              // Verificar si el comentario pertenece al usuario actual
              const isOwner = comment.user.id == userId;
              
              commentDiv.innerHTML = `
                <p>${comment.description}</p>
                <small>Por: ${comment.user.username || comment.user.id} ${comment.created_at ? "- " + formattedDate : ""}</small>
                ${isOwner ? `
                  <div class="comment-options">
                    <button class="edit-comment-btn" onclick="toggleCommentMenu(${comment.id})" title="Opciones">⋮</button>
                    <div id="commentMenu${comment.id}" class="comment-dropdown">
                      <button class="dropdown-item" onclick="editComment(${comment.id}, '${comment.description.replace(/'/g, "\\'")}')">
                        <i class="fas fa-edit"></i> Editar
                      </button>
                      <button class="dropdown-item" onclick="deleteComment(${comment.id})">
                        <i class="fas fa-trash"></i> Eliminar
                      </button>
                    </div>
                  </div>
                ` : ''}
              `;
              
              list.appendChild(commentDiv);
            });
          }
        } catch (error) {
          console.error("Error cargando comentarios:", error);
        }
      }

      async function postComment(postId) {
        const commentText = document.getElementById("modalNewComment");
        const submitBtn = document.getElementById("modalPostCommentBtn");
        
        if (!commentText || !submitBtn) return;
        
        // Validaciones del lado del cliente
        if (!commentText.value.trim()) {
          alert("El comentario no puede estar vacío");
          return;
        }
        
        if (commentText.value.trim().length < 3) {
          alert("El comentario debe tener al menos 3 caracteres");
          return;
        }
        
        if (commentText.value.length > 500) {
          alert("El comentario no puede exceder 500 caracteres");
          return;
        }
        
        // Deshabilitar botón para prevenir múltiples envíos
        submitBtn.disabled = true;
        submitBtn.textContent = "Publicando...";
        
        try {
          const res = await fetch(`${baseUrl}/news/posts/${postId}/comments`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ user_id: Number(userId), description: commentText.value.trim() })
          });
          
          const result = await res.json();
          
          if (res.ok && result.message) {
            commentText.value = "";
            loadComments(postId);
            alert("Comentario publicado exitosamente");
            submitBtn.disabled = false;
            submitBtn.textContent = "Publicar comentario";
          } else {
            alert(result.error || "Error al publicar el comentario");
            submitBtn.disabled = false;
            submitBtn.textContent = "Publicar comentario";
          }
        } catch (error) {
          console.error("Error publicando comentario:", error);
          alert("Error de conexión. Intenta nuevamente.");
          submitBtn.disabled = false;
          submitBtn.textContent = "Publicar comentario";
        }
      }

      // Funciones para el menú de comentarios
      window.toggleCommentMenu = function(commentId) {
        const menu = document.getElementById(`commentMenu${commentId}`);
        if (menu) {
          // Cerrar otros menús
          document.querySelectorAll('.comment-dropdown').forEach(dropdown => {
            if (dropdown.id !== `commentMenu${commentId}`) {
              dropdown.style.display = 'none';
            }
          });
          
          menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
      }

      window.editComment = function(commentId, currentText) {
        const commentDiv = document.querySelector(`[data-comment-id="${commentId}"]`);
        if (!commentDiv) return;
        
        const newText = prompt("Editar comentario:", currentText);
        if (newText && newText.trim() !== currentText && newText.trim().length >= 3) {
          updateCommentRequest(commentId, newText.trim());
        }
        
        // Cerrar el menú
        const menu = document.getElementById(`commentMenu${commentId}`);
        if (menu) menu.style.display = 'none';
      }

      window.deleteComment = function(commentId) {
        if (confirm("¿Estás seguro de que quieres eliminar este comentario?")) {
          deleteCommentRequest(commentId);
        }
        
        // Cerrar el menú
        const menu = document.getElementById(`commentMenu${commentId}`);
        if (menu) menu.style.display = 'none';
      }

      async function updateCommentRequest(commentId, newDescription) {
        try {
          const response = await fetch(`${baseUrl}/news/comments/${commentId}`, {
            method: "PUT",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ description: newDescription })
          });
          
          const result = await response.json();
          
          if (response.ok) {
            alert("Comentario actualizado exitosamente");
            // Recargar comentarios
            const postId = document.getElementById("commentsModal").dataset.postId;
            if (postId) loadComments(postId);
          } else {
            alert(result.error || "Error al actualizar el comentario");
          }
        } catch (error) {
          console.error("Error actualizando comentario:", error);
          alert("Error de conexión. Intenta nuevamente.");
        }
      }

      async function deleteCommentRequest(commentId) {
        try {
          const response = await fetch(`${baseUrl}/news/comments/${commentId}`, {
            method: "DELETE",
            headers: {
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            }
          });
          
          const result = await response.json();
          
          if (response.ok) {
            alert("Comentario eliminado exitosamente");
            // Recargar comentarios
            const postId = document.getElementById("commentsModal").dataset.postId;
            if (postId) loadComments(postId);
          } else {
            alert(result.error || "Error al eliminar el comentario");
          }
        } catch (error) {
          console.error("Error eliminando comentario:", error);
          alert("Error de conexión. Intenta nuevamente.");
        }
      }

      // Cerrar menús al hacer click fuera
      document.addEventListener("click", function(e) {
        if (!e.target.closest('.comment-options')) {
          document.querySelectorAll('.comment-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
          });
        }
      });

      // Cerrar modal al hacer click en el overlay
      document.getElementById("modalOverlay").addEventListener("click", closeCommentsModal);

      // Event listeners para comentarios
      document.querySelectorAll(".toggle-comments-btn").forEach(button => {
        button.addEventListener("click", function() {
          const postId = this.closest(".main-card").dataset.postId;
          if (!postId) return;
          openCommentsModal(postId);
        });
      });

      const modalPostBtn = document.getElementById("modalPostCommentBtn");
      const modalCloseBtn = document.getElementById("modalCloseBtn");
      
      if (modalPostBtn) {
        modalPostBtn.addEventListener("click", function() {
          const postId = document.getElementById("commentsModal").dataset.postId;
          if (postId) postComment(postId);
        });
      }

      if (modalCloseBtn) {
        modalCloseBtn.addEventListener("click", closeCommentsModal);
      }

      // Contador de caracteres para el textarea
      const modalNewComment = document.getElementById("modalNewComment");
      const charCounter = document.getElementById("charCounter");

      if (modalNewComment && charCounter) {
        modalNewComment.addEventListener("input", function() {
          const length = this.value.length;
          charCounter.textContent = `${length}/500`;
          
          // Cambiar color según la cantidad
          if (length > 450) {
            charCounter.style.color = "#ff5722"; // Rojo
          } else if (length > 350) {
            charCounter.style.color = "#ffa500"; // Naranja
          } else {
            charCounter.style.color = "#ffcb05"; // Amarillo
          }
          
          // Prevenir que se exceda el límite
          if (length >= 500) {
            this.value = this.value.substring(0, 500);
            charCounter.textContent = "500/500";
            charCounter.style.color = "#ff5722";
          }
        });
        
        // Prevenir redimensionamiento horizontal
        modalNewComment.addEventListener("mousedown", function(e) {
          if (e.offsetX > this.offsetWidth - 20) {
            e.preventDefault();
          }
        });
      }
    });
  </script>

</body>
</html>