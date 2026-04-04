import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';

class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final attState = ref.watch(attendanceProvider);

    return Scaffold(
      body: SafeArea(
        child: attState.error != null && attState.error!.contains('NOT_IMPLEMENTED')
            ? _buildStubScreen(context, ref)
            : attState.isLoading && attState.todayLog == null
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: () => ref.read(attendanceProvider.notifier).loadTodayData(),
                    child: ListView(
                      padding: const EdgeInsets.all(24.0),
                      children: [
                        _buildHeader(context, authState),
                        const SizedBox(height: 32),
                        _buildActionCard(context, ref, attState),
                        const SizedBox(height: 32),
                        _buildSummaryCard(context, attState),
                        const SizedBox(height: 32),
                        _buildActions(context, ref),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildStubScreen(BuildContext context, WidgetRef ref) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.build_circle_outlined, size: 64, color: Colors.grey),
          const SizedBox(height: 16),
          const Text('Fonction bientôt disponible', style: TextStyle(fontSize: 20)),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () {
              ref.read(attendanceProvider.notifier).loadTodayData();
            },
            child: const Text('Réessayer'),
          ),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () {
              ref.read(authProvider.notifier).logout();
            },
            child: const Text('Déconnexion', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, AuthState state) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Bonjour ${state.employee?.firstName ?? ''} 👋',
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          const Text('Leopardo RH', style: TextStyle(color: Colors.grey)),
        ],
      ),
    );
  }

  Widget _buildActionCard(BuildContext context, WidgetRef ref, AttendanceState state) {
    final isCheckedIn = state.todayLog?.checkIn != null && state.todayLog?.checkOut == null;
    
    return Container(
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          GestureDetector(
            onTap: state.isLoading
                ? null
                : () {
                    if (isCheckedIn) {
                      ref.read(attendanceProvider.notifier).checkOut();
                    } else {
                      ref.read(attendanceProvider.notifier).checkIn();
                    }
                  },
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isCheckedIn ? Theme.of(context).colorScheme.error : Theme.of(context).primaryColor,
                boxShadow: [
                  BoxShadow(
                    color: (isCheckedIn ? Theme.of(context).colorScheme.error : Theme.of(context).primaryColor).withOpacity(0.3),
                    blurRadius: 20,
                    spreadRadius: 5,
                  )
                ],
              ),
              child: Center(
                child: state.isLoading
                    ? const CircularProgressIndicator(color: Colors.white)
                    : Text(
                        isCheckedIn ? 'CHECK OUT' : 'CHECK IN',
                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
                      ),
              ),
            ),
          ),
          const SizedBox(height: 32),
          if (state.todayLog?.checkIn != null)
            Text(
              'Arrivée : ${state.todayLog!.checkIn!.hour.toString().padLeft(2, '0')}:${state.todayLog!.checkIn!.minute.toString().padLeft(2, '0')}',
              style: const TextStyle(fontSize: 18),
            ),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(BuildContext context, AttendanceState state) {
    if (state.summary == null) return const SizedBox.shrink();
    
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Text('💰 Gain estimé aujourd\'hui', style: TextStyle(fontSize: 16)),
              const Spacer(),
              Text('${state.summary!.totalEstimated.toStringAsFixed(0)} ${state.summary!.currency}',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Theme.of(context).primaryColor)),
            ],
          ),
          const SizedBox(height: 16),
          Text('⏱ Heures sup : ${state.summary!.overtimeGain > 0 ? (state.summary!.overtimeGain).toStringAsFixed(0) : "0h"}',
              style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 8),
          const Text('Estimation — net final calculé en fin de mois',
              style: TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic)),
        ],
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        OutlinedButton(
          onPressed: () => context.push('/history'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Voir historique'),
        ),
        const SizedBox(height: 16),
        TextButton(
          onPressed: () {
            ref.read(authProvider.notifier).logout();
          },
          child: const Text('Déconnexion', style: TextStyle(color: Colors.red)),
        ),
      ],
    );
  }
}
