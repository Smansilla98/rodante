import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../../src/auth/AuthContext';
import { roleLabel } from '../../src/auth/permissions';
import { APP_ENV, API_URL } from '../../src/config';
import { colors, space, type } from '../../src/theme';
import { PageHeader } from '../../src/ui/PageHeader';
import { Card, PrimaryButton, SectionLabel } from '../../src/ui/primitives';

export default function ProfileScreen() {
  const { user, logout } = useAuth();

  const onLogout = () => {
    Alert.alert(
      'Cerrar sesión',
      'Vas a salir de Rodante en este dispositivo. Vas a tener que volver a ingresar tu usuario y contraseña la próxima vez.',
      [
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Sí, cerrar sesión', style: 'destructive', onPress: () => void logout() },
      ],
    );
  };

  return (
    <View style={styles.root}>
      <PageHeader title="Perfil" showBack />
      <ScrollView contentContainerStyle={{ padding: space.lg, gap: space.md }}>
        <Card>
          <Text style={styles.name}>{user?.name}</Text>
          <Text style={styles.sub}>@{user?.username}</Text>
          <Text style={styles.sub}>Rol: {roleLabel(user?.role)}</Text>
          <Text style={styles.sub}>Empresa: {user?.company?.name ?? user?.company_id}</Text>
        </Card>

        <Card>
          <SectionLabel>Entorno</SectionLabel>
          <Text style={styles.sub}>APP_ENV: {APP_ENV}</Text>
          <Text style={styles.sub}>API: {API_URL}</Text>
        </Card>

        <PrimaryButton title="Cerrar sesión" icon="log-out-outline" onPress={onLogout} variant="danger" block />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.page },
  name: { color: colors.ink, fontSize: type.title, fontWeight: '700' },
  sub: { color: colors.muted, fontSize: type.body, marginTop: space.xs },
});
