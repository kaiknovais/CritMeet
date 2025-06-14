<?php
// components/tags/index.php
class RPGTags {
    
    public static function getAllTags() {
        return [
            // Sistemas de Jogo
            'D&D 5e', 'Pathfinder', 'Call of Cthulhu', 'Vampire: The Masquerade', 
            'GURPS', 'Fate', 'PbtA', 'Savage Worlds', 'Cyberpunk 2020/RED', 'Shadowrun',
            
            // Gêneros e Ambientações
            'Fantasia Medieval', 'Ficção Científica', 'Horror', 'Cyberpunk', 'Steampunk', 
            'Pós-apocalíptico', 'Faroeste', 'Histórico', 'Moderno', 'Superherói', 'Anime/Manga',
            
            // Estilos de Jogo
            'Roleplay Intenso', 'Combat-heavy', 'Exploração', 'Mistério/Investigação', 
            'Político/Intriga', 'Sandbox', 'Dungeon Crawling', 'Narrativo', 'Simulacionista', 'Casual',
            
            // Tons e Atmosfera
            'Sério/Dramático', 'Comédia', 'Dark/Sombrio', 'Heroico', 'Grimdark', 
            'Slice of Life', 'Épico', 'Intimista', 'Experimental',
            
            // Frequência e Formato
            'Semanal', 'Quinzenal', 'Mensal', 'One-shots', 'Campanhas Longas', 
            'Sessões 2-3h', 'Sessões 4-6h', 'Presencial', 'Online', 'Híbrido',
            
            // Preferências de Mesa
            'LGBTQ+ Friendly', 'Iniciante Friendly', 'Veteranos', 'Sem Romance', 
            'Romance Permitido', 'PvP Permitido', 'Sem PvP', 'Português', 'Inglês',
            
            // Horários
            'Manhã', 'Tarde', 'Noite', 'Fins de Semana', 'Dias Úteis', 'Horário Flexível'
        ];
    }
    
    public static function getTagsByCategory() {
        return [
            'Sistemas de Jogo' => [
                'D&D 5e', 'Pathfinder', 'Call of Cthulhu', 'Vampire: The Masquerade', 
                'GURPS', 'Fate', 'PbtA', 'Savage Worlds', 'Cyberpunk 2020/RED', 'Shadowrun'
            ],
            'Gêneros e Ambientações' => [
                'Fantasia Medieval', 'Ficção Científica', 'Horror', 'Cyberpunk', 'Steampunk', 
                'Pós-apocalíptico', 'Faroeste', 'Histórico', 'Moderno', 'Superherói', 'Anime/Manga'
            ],
            'Estilos de Jogo' => [
                'Roleplay Intenso', 'Combat-heavy', 'Exploração', 'Mistério/Investigação', 
                'Político/Intriga', 'Sandbox', 'Dungeon Crawling', 'Narrativo', 'Simulacionista', 'Casual'
            ],
            'Tons e Atmosfera' => [
                'Sério/Dramático', 'Comédia', 'Dark/Sombrio', 'Heroico', 'Grimdark', 
                'Slice of Life', 'Épico', 'Intimista', 'Experimental'
            ],
            'Frequência e Formato' => [
                'Semanal', 'Quinzenal', 'Mensal', 'One-shots', 'Campanhas Longas', 
                'Sessões 2-3h', 'Sessões 4-6h', 'Presencial', 'Online', 'Híbrido'
            ],
            'Preferências de Mesa' => [
                'LGBTQ+ Friendly', 'Iniciante Friendly', 'Veteranos', 'Sem Romance', 
                'Romance Permitido', 'PvP Permitido', 'Sem PvP', 'Português', 'Inglês'
            ],
            'Horários' => [
                'Manhã', 'Tarde', 'Noite', 'Fins de Semana', 'Dias Úteis', 'Horário Flexível'
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
        $all_tags = self::getAllTags();
        $selected_tags = is_string($selected_tags) ? self::parseUserTags($selected_tags) : $selected_tags;
        
        echo '<div class="tag-selector">';
        echo '<input type="hidden" name="' . $input_name . '" id="' . $input_name . '_hidden" value="' . self::formatUserTags($selected_tags) . '">';
        echo '<div class="selected-tags" id="selected-tags">';
        
        foreach ($selected_tags as $tag) {
            if (in_array($tag, $all_tags)) {
                echo '<span class="badge bg-primary me-2 mb-2 selected-tag" data-tag="' . htmlspecialchars($tag) . '">';
                echo htmlspecialchars($tag) . ' <button type="button" class="btn-close btn-close-white btn-sm ms-1" onclick="removeTag(this)"></button>';
                echo '</span>';
            }
        }
        echo '</div>';
        echo '<div class="available-tags mt-3">';
        echo '<h6>Tags Disponíveis (máximo 5):</h6>';
        
        foreach ($all_tags as $tag) {
            $is_selected = in_array($tag, $selected_tags);
            $class = $is_selected ? 'badge bg-secondary text-decoration-line-through' : 'badge bg-outline-primary';
            echo '<span class="' . $class . ' me-2 mb-2 available-tag" data-tag="' . htmlspecialchars($tag) . '" onclick="toggleTag(this)" style="cursor: pointer;">';
            echo htmlspecialchars($tag);
            echo '</span>';
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
                alert("Você pode selecionar no máximo 5 tags!");
            }
        }
        
        function addTag(tagName) {
            const selectedContainer = document.getElementById("selected-tags");
            const availableTag = document.querySelector(".available-tag[data-tag=\"" + tagName + "\"]");
            
            // Criar badge selecionada
            const selectedBadge = document.createElement("span");
            selectedBadge.className = "badge bg-primary me-2 mb-2 selected-tag";
            selectedBadge.setAttribute("data-tag", tagName);
            selectedBadge.innerHTML = tagName + " <button type=\"button\" class=\"btn-close btn-close-white btn-sm ms-1\" onclick=\"removeTag(this)\"></button>";
            
            selectedContainer.appendChild(selectedBadge);
            
            // Atualizar estilo da tag disponível
            availableTag.className = "badge bg-secondary text-decoration-line-through me-2 mb-2 available-tag";
            
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
            
            if (selectedBadge) {
                selectedBadge.remove();
            }
            
            if (availableTag) {
                availableTag.className = "badge bg-outline-primary me-2 mb-2 available-tag";
            }
            
            updateHiddenInput();
        }
        
        function updateHiddenInput() {
            const selectedTags = document.querySelectorAll(".selected-tag");
            const tagNames = Array.from(selectedTags).map(tag => tag.getAttribute("data-tag"));
            document.getElementById("' . $input_name . '_hidden").value = tagNames.join(",");
        }
        </script>';
        
        // CSS para as tags
        echo '<style>
        .tag-selector {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .selected-tags {
            min-height: 40px;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .available-tags {
            max-height: 300px;
            overflow-y: auto;
        }
        .badge.bg-outline-primary {
            background-color: transparent !important;
            border: 1px solid #0d6efd;
            color: #0d6efd;
        }
        .badge.bg-outline-primary:hover {
            background-color: #0d6efd !important;
            color: white;
        }
        .selected-tag {
            display: inline-flex;
            align-items: center;
        }
        .btn-close-white {
            filter: brightness(0) invert(1);
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