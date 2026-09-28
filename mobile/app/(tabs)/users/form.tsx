import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, ApiError } from '../../../src/api/client';
import type { UserRole } from '../../../src/api/types';
import { allRoles, roleLabel } from '../../../src/auth/permissions';
import { colors, space, type } from '../../../src/theme';
import { Card, Chip, Field, PrimaryButton } from '../../../src/ui/primitives';

export default function UserFormScreen() {
  const router = useRouter();
  const { userId } = useLocalSearchParams<{ userId?: string }>();
  const editing = userId != null && userId !== '';
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState<UserRole>('OPERARIO');
  const [active, setActive] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!editing) return;
    void api
      .users()
      .then((users) => {
        const current = users.find((item) => item.id === Number(userId));
        if (!current) {
          setError('No encontramos ese usuario.');
          return;
        }
        setName(current.name);
        setUsername(current.username);
        setRole(current.role);
        setActive(current.is_active);
      })
      .catch((e) => setError(e instanceof ApiError ? e.message : 'No se pudo cargar'));
  }, [editing, userId]);

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const body = {
        name: name.trim(),
        username: username.trim(),
        role,
        is_active: active,
        ...(password.trim() ? { password: password.trim() } : {}),
      };
      if (editing) {
        await api.updateUser(Number(userId), body);
      } else {
        if (!password.trim()) {
          setError('La contraseña es obligatoria para un usuario nuevo.');
          setBusy(false);
          return;
        }
        await api.createUser({ ...body, password: password.trim() });
      }
      Alert.alert('Listo', editing ? 'Usuario actualizado.' : 'Usuario creado.');
      router.back();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'No se pudo guardar');
    } finally {
      setBusy(false);
    }
  };

  const remove = () => {
    if (!editing) return;
    Alert.alert('Eliminar usuario', 'Si tiene historial queda inactivo.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar',
        style: 'destructive',
        onPress: () => {
          void (async () => {
            try {
              const result = await api.deleteUser(Number(userId));
              Alert.alert('Listo', result.message);
              router.back();
            } catch (e) {
              setError(e instanceof ApiError ? e.message : 'No se pudo eliminar');
            }
          })();
        },
      },
    ]);
  };

  return (
    <ScrollView contentContainerStyle={styles.scroll}>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <Card>
        <Field label="Nombre" value={name} onChangeText={setName} />
        <Field label="Usuario" value={username} onChangeText={setUsername} autoCapitalize="none" />
        <Field
          label={editing ? 'Contraseña nueva (opcional)' : 'Contraseña'}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
        />
        <Text style={styles.label}>Rol</Text>
        <View style={styles.chips}>
          {allRoles().map((item) => (
            <Chip key={item} label={roleLabel(item)} selected={role === item} onPress={() => setRole(item)} />
          ))}
        </View>
        {editing ? (
          <>
            <Text style={styles.label}>Estado</Text>
            <View style={styles.chips}>
              <Chip label="Activo" selected={active} onPress={() => setActive(true)} />
              <Chip label="Inactivo" selected={!active} onPress={() => setActive(false)} />
            </View>
          </>
        ) : null}
        <PrimaryButton title={editing ? 'Guardar' : 'Crear usuario'} onPress={() => void save()} loading={busy} block />
        {editing ? (
          <View style={{ marginTop: space.sm }}>
            <PrimaryButton title="Eliminar o inactivar" variant="outline" onPress={remove} block />
          </View>
        ) : null}
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: space.lg },
  label: { color: colors.muted, fontWeight: '700', marginTop: space.sm, marginBottom: space.xs },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: space.xs },
  error: { color: colors.danger, marginBottom: space.sm },
});
