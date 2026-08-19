@include('errors.layout', [
    'code' => '403',
    'title' => 'Sin permiso',
    'message' => 'Tu usuario no puede hacer esta operación. Pedile al administrador el rol adecuado.',
])
