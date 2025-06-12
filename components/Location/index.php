<?php

class Location {
    private $mysqli;
    private $user_id;
    
    public function __construct($mysqli, $user_id) {
        $this->mysqli = $mysqli;
        $this->user_id = $user_id;
    }
    
    public function handleLocationUpdate() {
        header('Content-Type: application/json');
        
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $accuracy = floatval($_POST['accuracy'] ?? 0);
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';
        $country = $_POST['country'] ?? 'Brasil';

        // Verificar se já existe localização para o usuário
        $check_sql = "SELECT id FROM user_locations WHERE user_id = ?";
        $check_stmt = $this->mysqli->prepare($check_sql);
        $check_stmt->bind_param("i", $this->user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Atualizar localização existente
            $update_sql = "UPDATE user_locations SET latitude = ?, longitude = ?, accuracy = ?, address = ?, city = ?, state = ?, country = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
            $update_stmt = $this->mysqli->prepare($update_sql);
            $update_stmt->bind_param("dddssssi", $latitude, $longitude, $accuracy, $address, $city, $state, $country, $this->user_id);
            
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Localização atualizada!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar localização']);
            }
        } else {
            // Inserir nova localização
            $insert_sql = "INSERT INTO user_locations (user_id, latitude, longitude, accuracy, address, city, state, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $this->mysqli->prepare($insert_sql);
            $insert_stmt->bind_param("idddssss", $this->user_id, $latitude, $longitude, $accuracy, $address, $city, $state, $country);
            
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Localização salva!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar localização']);
            }
        }
        exit();
    }
    
    public function getCurrentLocation() {
        if (!$this->user_id) return null;
        
        $location_sql = "SELECT latitude, longitude, accuracy, address, city, state, country, updated_at FROM user_locations WHERE user_id = ?";
        $location_stmt = $this->mysqli->prepare($location_sql);
        $location_stmt->bind_param("i", $this->user_id);
        $location_stmt->execute();
        $location_result = $location_stmt->get_result();
        
        if ($location_result && $location_row = $location_result->fetch_assoc()) {
            return $location_row;
        }
        
        return null;
    }
    
    public function render($current_location) {
        ?>
        <style>
            /* Estilos do mapa */
            #map {
                height: 400px;
                width: 100%;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .location-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 15px;
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .btn-location {
                background: linear-gradient(45deg, #4facfe, #00f2fe);
                border: none;
                color: white;
                font-weight: bold;
            }
            
            .btn-location:hover {
                background: linear-gradient(45deg, #00f2fe, #4facfe);
                color: white;
            }

            .controls-panel {
                background: white;
                border-radius: 15px;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
            }

            .location-info {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px;
                margin-top: 20px;
            }
        </style>
        
        <!-- Seção da Localização (Apenas do Usuário) -->
        <div class="collapse mt-3" id="mapSection">
            <div class="card card-body">
                <h5><i class="bi bi-geo-alt"></i> Minha Localização</h5>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Informações de Localização -->
                        <div class="location-card">
                            <h6>📍 Sua Localização Atual</h6>
                            <div id="location-status">
                                <?php if ($current_location): ?>
                                    <p class="mb-1"><strong>Endereço:</strong> <?php echo htmlspecialchars($current_location['address'] ?: 'Não especificado'); ?></p>
                                    <p class="mb-1"><strong>Cidade:</strong> <?php echo htmlspecialchars($current_location['city'] ?: 'N/A'); ?>, <?php echo htmlspecialchars($current_location['state'] ?: 'N/A'); ?></p>
                                    <p class="mb-0"><small>Última atualização: <?php echo date('d/m/Y H:i', strtotime($current_location['updated_at'])); ?></small></p>
                                <?php else: ?>
                                    <p class="mb-0">Localização não definida. Clique em "Obter Localização" para definir sua posição.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Controles -->
                        <div class="controls-panel">
                            <div class="row align-items-center">
                                <div class="col-md-12 text-center">
                                    <button id="get-location" class="btn btn-location btn-lg">
                                        <i class="bi bi-geo-alt-fill"></i> Obter Minha Localização
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mapa -->
                        <div id="map"></div>
                    </div>

                    <!-- Informações de Privacidade -->
                    <div class="col-md-4">
                        <div class="location-info">
                            <h6><i class="bi bi-shield-check"></i> Privacidade e Segurança</h6>
                            <p class="small text-muted">
                                <strong>🔐 Sua localização é privada:</strong><br>
                                • Apenas você pode ver sua posição no mapa<br>
                                • Sua localização não é compartilhada com outros usuários<br>
                                • Os dados são usados apenas para suas funcionalidades pessoais
                            </p>
                            
                            <h6 class="mt-4"><i class="bi bi-info-circle"></i> Como usar</h6>
                            <p class="small text-muted">
                                • Clique em "Obter Minha Localização" para definir sua posição<br>
                                • Use esta informação para organizar sessões presenciais<br>
                                • Encontre amigos através da busca por cidade/região
                            </p>

                            <?php if ($current_location): ?>
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6><i class="bi bi-bookmark-check"></i> Localização Salva</h6>
                                    <p class="small mb-1">
                                        <strong>Coordenadas:</strong><br>
                                        Lat: <?php echo number_format($current_location['latitude'], 6); ?><br>
                                        Lng: <?php echo number_format($current_location['longitude'], 6); ?>
                                    </p>
                                    <p class="small mb-0">
                                        <strong>Precisão:</strong> ±<?php echo intval($current_location['accuracy']); ?>m
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
?>