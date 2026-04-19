import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/features/settings/data/settings_repository.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final appPreferencesProvider = Provider<AppPreferences>((ref) {
  return AppPreferences();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  return ApiClient(
    storage,
    onUnauthorized: () {
      // Use microtask to avoid modifying providers during build phase
      Future.microtask(() {
        // dynamic to avoid circular import since auth_provider imports core_providers
        import('package:leopardo_rh/features/auth/providers/auth_provider.dart').then((m) {
           ref.read(m.authProvider.notifier).markSessionExpired();
        });
      });
    },
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthRepository(apiClient, storage);
});

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AttendanceRepository(apiClient);
});

final settingsRepositoryProvider = Provider<SettingsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return SettingsRepository(apiClient, preferences);
});
