import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import { colors, type } from '../../src/theme';

/**
 * Barra inferior con 5 accesos como máximo — más de eso es difícil de
 * escanear para alguien que no usa apps seguido. Órdenes de trabajo,
 * Inventario, Telemetría y Perfil siguen siendo rutas normales (se
 * navegan con `router.push`), pero se ocultan de la barra (`href: null`)
 * y se ofrecen desde la pantalla "Más".
 */
export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.muted,
        tabBarStyle: {
          backgroundColor: colors.sidebar,
          borderTopColor: colors.line,
          height: 76,
          paddingTop: 8,
          paddingBottom: 12,
        },
        tabBarLabelStyle: { fontSize: type.caption, fontWeight: '600' },
        tabBarItemStyle: { minHeight: 56 },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Inicio',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={26} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="lookup"
        options={{
          title: 'Buscar',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'search' : 'search-outline'} size={26} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="tires"
        options={{
          title: 'Neumáticos',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'ellipse' : 'ellipse-outline'} size={26} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="units"
        options={{
          title: 'Unidades',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'bus' : 'bus-outline'} size={26} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'Más',
          tabBarIcon: ({ color, focused }) => (
            <Ionicons name={focused ? 'menu' : 'menu-outline'} size={26} color={color} />
          ),
        }}
      />

      {/* Rutas alcanzables desde "Más" — ocultas de la barra inferior. */}
      <Tabs.Screen name="work-orders" options={{ href: null }} />
      <Tabs.Screen name="inventory-sessions" options={{ href: null }} />
      <Tabs.Screen name="telemetry" options={{ href: null }} />
      <Tabs.Screen name="profile" options={{ href: null }} />
    </Tabs>
  );
}
