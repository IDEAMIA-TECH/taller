# API de Taller Mecánico

## Autenticación

Todas las peticiones a la API requieren un token de autenticación en el header `Authorization`:

```
Authorization: Bearer <token>
```

## Endpoints

### Vehículos

#### Listar vehículos
```
GET /api/v1/vehicles
```

Respuesta:
```json
[
    {
        "id_vehicle": 1,
        "id_client": 1,
        "brand": "Toyota",
        "model": "Corolla",
        "year": 2020,
        "color": "Blanco",
        "plates": "ABC123",
        "vin": "1HGCM82633A123456",
        "last_mileage": 50000,
        "client_name": "Juan Pérez"
    }
]
```

#### Obtener vehículo
```
GET /api/v1/vehicles/{id}
```

Respuesta:
```json
{
    "id_vehicle": 1,
    "id_client": 1,
    "brand": "Toyota",
    "model": "Corolla",
    "year": 2020,
    "color": "Blanco",
    "plates": "ABC123",
    "vin": "1HGCM82633A123456",
    "last_mileage": 50000,
    "client_name": "Juan Pérez"
}
```

#### Crear vehículo
```
POST /api/v1/vehicles
```

Body:
```json
{
    "id_client": 1,
    "brand": "Toyota",
    "model": "Corolla",
    "year": 2020,
    "color": "Blanco",
    "plates": "ABC123",
    "vin": "1HGCM82633A123456",
    "last_mileage": 50000
}
```

#### Actualizar vehículo
```
PUT /api/v1/vehicles/{id}
```

Body:
```json
{
    "color": "Negro",
    "last_mileage": 55000
}
```

#### Eliminar vehículo
```
DELETE /api/v1/vehicles/{id}
```

### Servicios

#### Listar servicios
```
GET /api/v1/services
```

Respuesta:
```json
[
    {
        "id_service": 1,
        "name": "Cambio de aceite",
        "description": "Cambio de aceite y filtro",
        "price": 500.00,
        "duration": 60,
        "status": "active"
    }
]
```

#### Obtener servicio
```
GET /api/v1/services/{id}
```

#### Crear servicio
```
POST /api/v1/services
```

Body:
```json
{
    "name": "Cambio de aceite",
    "description": "Cambio de aceite y filtro",
    "price": 500.00,
    "duration": 60
}
```

#### Actualizar servicio
```
PUT /api/v1/services/{id}
```

#### Eliminar servicio
```
DELETE /api/v1/services/{id}
```

### Órdenes de Servicio

#### Listar órdenes
```
GET /api/v1/orders
```

Respuesta:
```json
[
    {
        "id_order": 1,
        "order_number": "ORD-2023-001",
        "id_vehicle": 1,
        "id_client": 1,
        "status": "completed",
        "total_amount": 1500.00,
        "created_at": "2023-01-01 10:00:00",
        "completed_at": "2023-01-01 12:00:00"
    }
]
```

#### Obtener orden
```
GET /api/v1/orders/{id}
```

#### Crear orden
```
POST /api/v1/orders
```

Body:
```json
{
    "id_vehicle": 1,
    "services": [
        {
            "id_service": 1,
            "quantity": 1
        }
    ],
    "notes": "Cambio de aceite programado"
}
```

#### Actualizar orden
```
PUT /api/v1/orders/{id}
```

#### Eliminar orden
```
DELETE /api/v1/orders/{id}
```

### Recordatorios

#### Listar recordatorios
```
GET /api/v1/reminders
```

Respuesta:
```json
[
    {
        "id_reminder": 1,
        "id_vehicle": 1,
        "id_service": 1,
        "reminder_type": "mileage",
        "due_mileage": 60000,
        "status": "pending",
        "created_at": "2023-01-01 10:00:00"
    }
]
```

#### Obtener recordatorio
```
GET /api/v1/reminders/{id}
```

#### Crear recordatorio
```
POST /api/v1/reminders
```

Body:
```json
{
    "id_vehicle": 1,
    "id_service": 1,
    "reminder_type": "mileage",
    "due_mileage": 60000,
    "notes": "Recordatorio de cambio de aceite"
}
```

#### Actualizar recordatorio
```
PUT /api/v1/reminders/{id}
```

#### Eliminar recordatorio
```
DELETE /api/v1/reminders/{id}
```

## Códigos de Estado

- 200: OK
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Internal Server Error

## Ejemplos de Uso

### JavaScript (Fetch)
```javascript
const API_URL = 'https://taller.example.com/api/v1';

async function getVehicles() {
    const response = await fetch(`${API_URL}/vehicles`, {
        headers: {
            'Authorization': 'Bearer your-token-here'
        }
    });
    
    if (!response.ok) {
        throw new Error('Error al obtener vehículos');
    }
    
    return await response.json();
}
```

### Python (Requests)
```python
import requests

API_URL = 'https://taller.example.com/api/v1'
headers = {
    'Authorization': 'Bearer your-token-here'
}

def get_vehicles():
    response = requests.get(f'{API_URL}/vehicles', headers=headers)
    response.raise_for_status()
    return response.json()
```

## Seguridad

- Todos los endpoints requieren autenticación mediante token
- Los tokens expiran después de 24 horas
- Se recomienda usar HTTPS en todas las peticiones
- No compartir tokens en código público o repositorios
- Rotar tokens regularmente por seguridad 