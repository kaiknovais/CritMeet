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
        <!-- Incluir CSS do Leaflet -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        
        <style>
            /* Estilos do mapa */
            #map {
                height: 400px;
                width: 100%;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                background-color: #f8f9fa;
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

            /* Correção para o Leaflet */
            .leaflet-container {
                height: 400px;
                width: 100%;
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Variáveis globais do mapa
        window.locationMap = window.locationMap || {};
        let map = window.locationMap.map;
        let userMarker = window.locationMap.userMarker;
        let mapInitialized = window.locationMap.initialized || false;

        // Aguardar carregamento completo do DOM e do Leaflet
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se o Leaflet está carregado
            if (typeof L === 'undefined') {
                console.error('Leaflet não está carregado!');
                return;
            }

            // Inicializar o mapa quando a seção for expandida
            const mapSection = document.getElementById('mapSection');
            if (mapSection) {
                mapSection.addEventListener('shown.bs.collapse', function () {
                    setTimeout(function() {
                        if (!mapInitialized) {
                            initializeMap();
                        } else if (map) {
                            map.invalidateSize();
                        }
                    }, 250); // Aguardar um pouco para a animação terminar
                });
            }

            // Event listener para o botão de localização
            const getLocationBtn = document.getElementById('get-location');
            if (getLocationBtn) {
                getLocationBtn.addEventListener('click', getUserLocation);
            }
        });

        function initializeMap() {
            try {
                const mapElement = document.getElementById('map');
                if (!mapElement) {
                    console.error('Elemento do mapa não encontrado!');
                    return;
                }

                // Limpar mapa existente se houver
                if (map) {
                    map.remove();
                }

                // Coordenadas padrão para São Paulo caso não tenha localização
                let defaultLat = -23.5505;
                let defaultLng = -46.6333;
                let defaultZoom = 10;

                <?php if ($current_location): ?>
                    defaultLat = <?php echo $current_location['latitude']; ?>;
                    defaultLng = <?php echo $current_location['longitude']; ?>;
                    defaultZoom = 15;
                <?php endif; ?>

                // Criar o mapa
                map = L.map('map', {
                    center: [defaultLat, defaultLng],
                    zoom: defaultZoom,
                    zoomControl: true,
                    scrollWheelZoom: true
                });

                // Adicionar tiles do OpenStreetMap
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(map);

                // Se já tem localização salva, adicionar marcador
                <?php if ($current_location): ?>
                    userMarker = L.marker([defaultLat, defaultLng])
                        .addTo(map)
                        .bindPopup('Sua localização atual')
                        .openPopup();
                <?php endif; ?>

                // Forçar redimensionamento do mapa após um tempo
                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                    }
                }, 500);

                // Armazenar estado global
                window.locationMap = {
                    map: map,
                    userMarker: userMarker,
                    initialized: true
                };

                mapInitialized = true;
                console.log('Mapa inicializado com sucesso!');

            } catch (error) {
                console.error('Erro ao inicializar mapa:', error);
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    mapElement.innerHTML = '<div class="alert alert-danger">Erro ao carregar o mapa. Tente recarregar a página.</div>';
                }
            }
        }

        // Função para obter localização do usuário
        function getUserLocation() {
            const button = document.getElementById('get-location');
            if (!button) return;

            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="bi bi-geo-alt-fill"></i> Obtendo localização...';
            button.disabled = true;

            if (!navigator.geolocation) {
                alert('Geolocalização não é suportada neste navegador');
                button.innerHTML = originalText;
                button.disabled = false;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    // Inicializar mapa se não estiver inicializado
                    if (!mapInitialized || !map) {
                        initializeMap();
                        // Aguardar um pouco para o mapa inicializar
                        setTimeout(() => updateMapLocation(lat, lng, accuracy), 500);
                    } else {
                        updateMapLocation(lat, lng, accuracy);
                    }

                    button.innerHTML = originalText;
                    button.disabled = false;
                },
                function(error) {
                    console.error('Erro ao obter localização:', error);
                    let errorMessage = 'Erro desconhecido';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Permissão de localização negada';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Localização indisponível';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Tempo limite excedido';
                            break;
                    }
                    
                    alert('Erro ao obter localização: ' + errorMessage);
                    button.innerHTML = originalText;
                    button.disabled = false;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 300000
                }
            );
        }

        function updateMapLocation(lat, lng, accuracy) {
            if (!map) {
                console.error('Mapa não inicializado');
                return;
            }

            // Atualizar o mapa
            map.setView([lat, lng], 15);

            // Remover marcador anterior se existir
            if (userMarker) {
                map.removeLayer(userMarker);
            }

            // Adicionar novo marcador
            userMarker = L.marker([lat, lng])
                .addTo(map)
                .bindPopup('Sua localização atual')
                .openPopup();

            // Atualizar estado global
            window.locationMap.userMarker = userMarker;

            // Fazer geocoding reverso para obter endereço
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    const address = data.display_name || '';
                    const city = data.address?.city || data.address?.town || data.address?.village || '';
                    const state = data.address?.state || '';
                    
                    // Salvar localização no servidor
                    saveLocation(lat, lng, accuracy, address, city, state);
                })
                .catch(error => {
                    console.error('Erro no geocoding:', error);
                    // Salvar mesmo sem endereço
                    saveLocation(lat, lng, accuracy, '', '', '');
                });
        }

        function saveLocation(lat, lng, accuracy, address, city, state) {
            const formData = new FormData();
            formData.append('action', 'update_location');
            formData.append('latitude', lat);
            formData.append('longitude', lng);
            formData.append('accuracy', accuracy);
            formData.append('address', address);
            formData.append('city', city);
            formData.append('state', state);
            formData.append('country', 'Brasil');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar informações na tela
                    const locationStatus = document.getElementById('location-status');
                    if (locationStatus) {
                        locationStatus.innerHTML = `
                            <p class="mb-1"><strong>Endereço:</strong> ${address || 'Não especificado'}</p>
                            <p class="mb-1"><strong>Cidade:</strong> ${city || 'N/A'}, ${state || 'N/A'}</p>
                            <p class="mb-0"><small>Última atualização: ${new Date().toLocaleString('pt-BR')}</small></p>
                        `;
                    }
                    
                    // Mostrar mensagem de sucesso
                    showSuccessMessage(data.message);
                } else {
                    alert('Erro ao salvar localização: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao salvar localização:', error);
                alert('Erro ao salvar localização');
            });
        }

        function showSuccessMessage(message) {
            const locationCard = document.querySelector('.location-card');
            if (!locationCard) return;

            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
            successAlert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            locationCard.appendChild(successAlert);
            
            // Remover o alerta após 3 segundos
            setTimeout(() => {
                if (successAlert.parentNode) {
                    successAlert.parentNode.removeChild(successAlert);
                }
            }, 3000);
        }
        </script>
        <?php
    }
}
?>