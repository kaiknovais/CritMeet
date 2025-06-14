<?php
class RPGTags {
    
    public static function getAllTags() {
        return [
            // Sistemas de Jogo
            'D&D 5e', 'Pathfinder', 'Call of Cthulhu', 'Vampire: The Masquerade', 
            'GURPS', 'Fate', 'PbtA', 'Savage Worlds', 'Cyberpunk 2020/RED', 'Shadowrun',
            'Tormenta20', 'Old Dragon', 'Dungeon World', 'Blades in the Dark', 'World of Darkness',
            'Mutants & Masterminds', 'RIFTS', 'Traveller', 'Star Wars RPG', 'Marvel Super Heroes',
            
            // Gêneros e Ambientações
            'Fantasia Medieval', 'Ficção Científica', 'Horror', 'Cyberpunk', 'Steampunk', 
            'Pós-apocalíptico', 'Faroeste', 'Histórico', 'Moderno', 'Superherói', 'Anime/Manga',
            'Space Opera', 'Investigação Sobrenatural', 'Thriller', 'Aventura', 'Comédia',
            'Pirata', 'Noir', 'Distopia', 'Utopia', 'Alternate History', 'Weird West',
            
            // Estilos de Jogo
            'Roleplay Intenso', 'Combat-heavy', 'Exploração', 'Mistério/Investigação', 
            'Político/Intriga', 'Sandbox', 'Dungeon Crawling', 'Narrativo', 'Simulacionista', 'Casual',
            'Tactical Combat', 'Social Encounters', 'Survival', 'Base Building', 'Hexcrawl',
            'Megadungeon', 'Theater of Mind', 'Miniatures', 'Props & Handouts',
            
            // Tons e Atmosfera
            'Sério/Dramático', 'Comédia', 'Dark/Sombrio', 'Heroico', 'Grimdark', 
            'Slice of Life', 'Épico', 'Intimista', 'Experimental', 'Wholesome',
            'Body Horror', 'Cosmic Horror', 'Psychological', 'Action-packed', 'Relaxed',
            
            // Frequência e Formato
            'Semanal', 'Quinzenal', 'Mensal', 'One-shots', 'Campanhas Longas', 
            'Sessões 2-3h', 'Sessões 4-6h', 'Sessões 6h+', 'Presencial', 'Online', 'Híbrido',
            'West Marches', 'Drop-in/Drop-out', 'Torneios', 'Eventos Especiais',
            
            // Preferências de Mesa
            'LGBTQ+ Friendly', 'Iniciante Friendly', 'Veteranos', 'Sem Romance', 
            'Romance Permitido', 'PvP Permitido', 'Sem PvP', 'Português', 'Inglês',
            'Conteúdo Adulto', 'Family Friendly', 'Linha X', 'Session Zero', 'Safety Tools',
            'Inclusivo', 'Acessível', 'Neurodivergente Friendly',
            
            // Horários
            'Manhã', 'Tarde', 'Noite', 'Madrugada', 'Fins de Semana', 'Dias Úteis', 
            'Horário Flexível', 'Fuso Horário Brasília', 'Múltiplos Fusos'
        ];
    }
    
    public static function getTagsByCategory() {
        return [
            'Sistemas de Jogo' => [
                'D&D 5e', 'Pathfinder', 'Call of Cthulhu', 'Vampire: The Masquerade', 
                'GURPS', 'Fate', 'PbtA', 'Savage Worlds', 'Cyberpunk 2020/RED', 'Shadowrun',
                'Tormenta20', 'Old Dragon', 'Dungeon World', 'Blades in the Dark', 'World of Darkness',
                'Mutants & Masterminds', 'RIFTS', 'Traveller', 'Star Wars RPG', 'Marvel Super Heroes'
            ],
            'Gêneros e Ambientações' => [
                'Fantasia Medieval', 'Ficção Científica', 'Horror', 'Cyberpunk', 'Steampunk', 
                'Pós-apocalíptico', 'Faroeste', 'Histórico', 'Moderno', 'Superherói', 'Anime/Manga',
                'Space Opera', 'Investigação Sobrenatural', 'Thriller', 'Aventura', 'Comédia',
                'Pirata', 'Noir', 'Distopia', 'Utopia', 'Alternate History', 'Weird West'
            ],
            'Estilos de Jogo' => [
                'Roleplay Intenso', 'Combat-heavy', 'Exploração', 'Mistério/Investigação', 
                'Político/Intriga', 'Sandbox', 'Dungeon Crawling', 'Narrativo', 'Simulacionista', 'Casual',
                'Tactical Combat', 'Social Encounters', 'Survival', 'Base Building', 'Hexcrawl',
                'Megadungeon', 'Theater of Mind', 'Miniatures', 'Props & Handouts'
            ],
            'Tons e Atmosfera' => [
                'Sério/Dramático', 'Comédia', 'Dark/Sombrio', 'Heroico', 'Grimdark', 
                'Slice of Life', 'Épico', 'Intimista', 'Experimental', 'Wholesome',
                'Body Horror', 'Cosmic Horror', 'Psychological', 'Action-packed', 'Relaxed'
            ],
            'Frequência e Formato' => [
                'Semanal', 'Quinzenal', 'Mensal', 'One-shots', 'Campanhas Longas', 
                'Sessões 2-3h', 'Sessões 4-6h', 'Sessões 6h+', 'Presencial', 'Online', 'Híbrido',
                'West Marches', 'Drop-in/Drop-out', 'Torneios', 'Eventos Especiais'
            ],
            'Preferências de Mesa' => [
                'LGBTQ+ Friendly', 'Iniciante Friendly', 'Veteranos', 'Sem Romance', 
                'Romance Permitido', 'PvP Permitido', 'Sem PvP', 'Português', 'Inglês',
                'Conteúdo Adulto', 'Family Friendly', 'Linha X', 'Session Zero', 'Safety Tools',
                'Inclusivo', 'Acessível', 'Neurodivergente Friendly'
            ],
            'Horários' => [
                'Manhã', 'Tarde', 'Noite', 'Madrugada', 'Fins de Semana', 'Dias Úteis', 
                'Horário Flexível', 'Fuso Horário Brasília', 'Múltiplos Fusos'
            ]
        ];
    }
    
    public static function parseUserTags($preferences_string) {
        if (empty($preferences_string)) {
            return [];
        }
        return array_map('trim', explode(',', $preferences_string));
    }
    
    public static function formatUserTags($tags_array) {
        if (empty($tags_array) || !is_array($tags_array)) {
            return '';
        }
        return implode(',', array_slice($tags_array, 0, 5)); // Limite de 5 tags
    }
    
    public static function renderTagSelector($selected_tags = [], $input_name = 'preferences') {
        $tags_by_category = self::getTagsByCategory();
        $selected_tags = is_string($selected_tags) ? self::parseUserTags($selected_tags) : $selected_tags;
        
        echo '<div class="advanced-tag-selector">';
        echo '<input type="hidden" name="' . $input_name . '" id="' . $input_name . '_hidden" value="' . self::formatUserTags($selected_tags) . '">';
        
        // Área de tags selecionadas
        echo '<div class="selected-tags-area">';
        echo '<h6 class="mb-2"><i class="bi bi-check-circle"></i> Tags Selecionadas (<span id="selected-count">' . count($selected_tags) . '</span>/5):</h6>';
        echo '<div class="selected-tags" id="selected-tags">';
        
        foreach ($selected_tags as $tag) {
            if (in_array($tag, self::getAllTags())) {
                echo '<span class="badge bg-primary me-2 mb-2 selected-tag" data-tag="' . htmlspecialchars($tag) . '">';
                echo htmlspecialchars($tag) . ' <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removeTag(this)"></button>';
                echo '</span>';
            }
        }
        
        if (empty($selected_tags)) {
            echo '<p class="text-muted mb-0" id="no-tags-message">Nenhuma tag selecionada</p>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Controles de pesquisa e filtro
        echo '<div class="tag-controls mt-3">';
        echo '<div class="row g-2">';
        echo '<div class="col-md-8">';
        echo '<input type="text" class="form-control" id="tag-search" placeholder="🔍 Pesquisar tags..." oninput="filterTags()">';
        echo '</div>';
        echo '<div class="col-md-4">';
        echo '<select class="form-select" id="category-filter" onchange="filterTags()">';
        echo '<option value="">Todas as Categorias</option>';
        foreach ($tags_by_category as $category => $tags) {
            echo '<option value="' . htmlspecialchars($category) . '">' . htmlspecialchars($category) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Área de tags disponíveis por categoria
        echo '<div class="available-tags-area mt-3">';
        echo '<h6 class="mb-3"><i class="bi bi-tags"></i> Tags Disponíveis:</h6>';
        
        foreach ($tags_by_category as $category => $tags) {
            echo '<div class="tag-category" data-category="' . htmlspecialchars($category) . '">';
            echo '<h6 class="category-title">' . htmlspecialchars($category) . ' <span class="badge bg-secondary category-count">(' . count($tags) . ')</span></h6>';
            echo '<div class="category-tags">';
            
            foreach ($tags as $tag) {
                $is_selected = in_array($tag, $selected_tags);
                $class = $is_selected ? 'badge bg-secondary text-decoration-line-through' : 'badge bg-outline-primary';
                echo '<span class="' . $class . ' me-2 mb-2 available-tag" data-tag="' . htmlspecialchars($tag) . '" data-category="' . htmlspecialchars($category) . '" onclick="toggleTag(this)" style="cursor: pointer;">';
                echo htmlspecialchars($tag);
                echo '</span>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // JavaScript para funcionalidade das tags
        echo '<script>
        function toggleTag(element) {
            const tag = element.getAttribute("data-tag");
            const selectedContainer = document.getElementById("selected-tags");
            const selectedTags = selectedContainer.querySelectorAll(".selected-tag");
            
            if (element.classList.contains("bg-secondary")) {
                // Tag já selecionada, remover
                removeTagByName(tag);
            } else if (selectedTags.length < 5) {
                // Adicionar tag (limite de 5)
                addTag(tag);
            } else {
                showAlert("Você pode selecionar no máximo 5 tags!", "warning");
            }
        }
        
        function addTag(tagName) {
            const selectedContainer = document.getElementById("selected-tags");
            const availableTag = document.querySelector(".available-tag[data-tag=\"" + tagName + "\"]");
            const noTagsMessage = document.getElementById("no-tags-message");
            
            // Remover mensagem "nenhuma tag selecionada"
            if (noTagsMessage) {
                noTagsMessage.remove();
            }
            
            // Criar badge selecionada
            const selectedBadge = document.createElement("span");
            selectedBadge.className = "badge bg-primary me-2 mb-2 selected-tag";
            selectedBadge.setAttribute("data-tag", tagName);
            selectedBadge.innerHTML = tagName + " <button type=\"button\" class=\"btn-close btn-close-white btn-sm ms-1\" onclick=\"removeTag(this)\"></button>";
            
            selectedContainer.appendChild(selectedBadge);
            
            // Atualizar estilo da tag disponível
            if (availableTag) {
                availableTag.className = "badge bg-secondary text-decoration-line-through me-2 mb-2 available-tag";
            }
            
            updateSelectedCount();
            updateHiddenInput();
        }
        
        function removeTag(button) {
            const badge = button.parentElement;
            const tagName = badge.getAttribute("data-tag");
            removeTagByName(tagName);
        }
        
        function removeTagByName(tagName) {
            const selectedBadge = document.querySelector(".selected-tag[data-tag=\"" + tagName + "\"]");
            const availableTag = document.querySelector(".available-tag[data-tag=\"" + tagName + "\"]");
            const selectedContainer = document.getElementById("selected-tags");
            
            if (selectedBadge) {
                selectedBadge.remove();
            }
            
            if (availableTag) {
                availableTag.className = "badge bg-outline-primary me-2 mb-2 available-tag";
            }
            
            // Adicionar mensagem se não houver tags selecionadas
            const remainingTags = selectedContainer.querySelectorAll(".selected-tag");
            if (remainingTags.length === 0) {
                const noTagsMessage = document.createElement("p");
                noTagsMessage.className = "text-muted mb-0";
                noTagsMessage.id = "no-tags-message";
                noTagsMessage.textContent = "Nenhuma tag selecionada";
                selectedContainer.appendChild(noTagsMessage);
            }
            
            updateSelectedCount();
            updateHiddenInput();
        }
        
        function updateSelectedCount() {
            const selectedTags = document.querySelectorAll(".selected-tag");
            document.getElementById("selected-count").textContent = selectedTags.length;
        }
        
        function updateHiddenInput() {
            const selectedTags = document.querySelectorAll(".selected-tag");
            const tagNames = Array.from(selectedTags).map(tag => tag.getAttribute("data-tag"));
            document.getElementById("' . $input_name . '_hidden").value = tagNames.join(",");
        }
        
        function filterTags() {
            const searchTerm = document.getElementById("tag-search").value.toLowerCase();
            const selectedCategory = document.getElementById("category-filter").value;
            const categories = document.querySelectorAll(".tag-category");
            
            categories.forEach(category => {
                const categoryName = category.getAttribute("data-category");
                const shouldShowCategory = !selectedCategory || selectedCategory === categoryName;
                
                if (shouldShowCategory) {
                    const tags = category.querySelectorAll(".available-tag");
                    let visibleTagsCount = 0;
                    
                    tags.forEach(tag => {
                        const tagText = tag.textContent.toLowerCase();
                        const matchesSearch = !searchTerm || tagText.includes(searchTerm);
                        
                        if (matchesSearch) {
                            tag.style.display = "inline-block";
                            visibleTagsCount++;
                        } else {
                            tag.style.display = "none";
                        }
                    });
                    
                    // Atualizar contador da categoria
                    const categoryCount = category.querySelector(".category-count");
                    if (categoryCount) {
                        categoryCount.textContent = "(" + visibleTagsCount + ")";
                    }
                    
                    category.style.display = visibleTagsCount > 0 ? "block" : "none";
                } else {
                    category.style.display = "none";
                }
            });
        }
        
        function showAlert(message, type = "info") {
            // Criar alerta Bootstrap
            const alertDiv = document.createElement("div");
            alertDiv.className = "alert alert-" + type + " alert-dismissible fade show mt-2";
            alertDiv.innerHTML = message + "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>";
            
            // Inserir no topo do seletor
            const selector = document.querySelector(".advanced-tag-selector");
            selector.insertBefore(alertDiv, selector.firstChild);
            
            // Auto-remover após 3 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
        </script>';
        
        // CSS para o seletor avançado
        echo '<style>
        .advanced-tag-selector {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .selected-tags-area {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 2px solid #007bff;
            margin-bottom: 15px;
            box-shadow: inset 0 2px 4px rgba(0,123,255,0.1);
        }
        
        .selected-tags {
            min-height: 45px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 5px;
        }
        
        .tag-controls {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #dee2e6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .available-tags-area {
            background: white;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #dee2e6;
            max-height: 500px;
            overflow-y: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .tag-category {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .category-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .badge.bg-outline-primary {
            background-color: transparent !important;
            border: 2px solid #007bff;
            color: #007bff;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .badge.bg-outline-primary:hover {
            background-color: #007bff !important;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,123,255,0.3);
        }
        
        .selected-tag {
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-close-white {
            filter: brightness(0) invert(1);
        }
        
        .available-tag {
            cursor: pointer;
            user-select: none;
        }
        
        .available-tag.bg-secondary {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        #tag-search {
            border: 2px solid #dee2e6;
            transition: border-color 0.2s ease;
        }
        
        #tag-search:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .category-count {
            font-size: 0.75em;
            opacity: 0.8;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .advanced-tag-selector {
                padding: 15px;
            }
            
            .tag-category {
                padding: 10px;
            }
            
            .available-tags-area {
                max-height: 400px;
            }
        }
        </style>';
    }
    
    public static function renderTagsDisplay($preferences_string, $max_display = 5) {
        $tags = self::parseUserTags($preferences_string);
        
        if (empty($tags)) {
            echo '<span class="text-muted">Nenhuma preferência selecionada</span>';
            return;
        }
        
        $displayed_tags = array_slice($tags, 0, $max_display);
        $remaining_count = count($tags) - count($displayed_tags);
        
        foreach ($displayed_tags as $tag) {
            echo '<span class="badge bg-primary me-1 mb-1">' . htmlspecialchars($tag) . '</span>';
        }
        
        if ($remaining_count > 0) {
            echo '<span class="badge bg-secondary me-1 mb-1">+' . $remaining_count . ' mais</span>';
        }
    }
}
?>