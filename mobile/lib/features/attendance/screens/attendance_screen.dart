import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:leopardo_rh/core/widgets/pulse_button.dart';
import 'package:leopardo_rh/features/attendance/providers/attendance_provider.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/models/employee.dart';
import 'package:leopardo_rh/models/team_overview.dart';

class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final attState = ref.watch(attendanceProvider);
    final employee = authState.employee;
    final canSupervise = employee?.isSupervisor == true;

    return Scaffold(
      body: SafeArea(
        child: attState.error != null && attState.error!.contains('NOT_IMPLEMENTED')
            ? _buildStubScreen(context, ref)
            : Column(
                children: [
                   if (const String.fromEnvironment('API_BASE_URL') == 'mock')
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                      color: Colors.red.shade900,
                      child: const Text(
                        'MODE DÉMO ACTIF (DONNÉES FICTIVES)',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ),
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: () => ref.read(attendanceProvider.notifier).loadTodayData(),
                      child: ListView(
                        padding: const EdgeInsets.all(24.0),
                        children: [
                          _buildHeader(context, authState, employee),
                          const SizedBox(height: 32),
                          _buildActionCard(context, ref, attState),
                          const SizedBox(height: 32),
                          _buildSummaryCard(context, attState),
                          if (canSupervise) ...[
                            const SizedBox(height: 32),
                            _buildSupervisorDashboard(context, ref, attState, employee!),
                          ],
                          if (attState.notice != null) ...[
                            const SizedBox(height: 32),
                            _buildNoticeCard(context, attState.notice!),
                          ],
                          const SizedBox(height: 32),
                          _buildActions(context, ref, attState),
                        ],
                      ),
                    ),
                  ),
                ],
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
          const Text('Fonction bientot disponible', style: TextStyle(fontSize: 20)),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () => ref.read(attendanceProvider.notifier).loadTodayData(),
            child: const Text('Reessayer'),
          ),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () => ref.read(authProvider.notifier).logout(),
            child: const Text('Deconnexion', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, AuthState state, Employee? employee) {
    final roleLabel = employee?.isHrManager == true
        ? 'Espace RH'
        : employee?.isSupervisor == true
            ? 'Espace manager'
            : 'Espace employe';

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Bonjour ${state.employee?.firstName ?? ''}',
            style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(roleLabel, style: const TextStyle(color: Colors.grey)),
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
          if (state.isLoading && state.todayLog == null)
            Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Theme.of(context).primaryColor.withValues(alpha: 0.12),
              ),
              child: const Center(
                child: SizedBox(
                  height: 28,
                  width: 28,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            )
          else
            PulseButton(
              isCheckedIn: isCheckedIn,
              isLoading: state.isLoading,
              onTap: () {
                if (isCheckedIn) {
                  ref.read(attendanceProvider.notifier).checkOut();
                } else {
                  ref.read(attendanceProvider.notifier).checkIn();
                }
              },
            ),
          const SizedBox(height: 32),
          if (state.isLoading && state.todayLog == null) ...[
            const Text(
              'Chargement de votre presence du jour...',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
          ] else if (state.error != null && state.todayLog == null) ...[
            Text(
              state.error!,
              textAlign: TextAlign.center,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => ref.read(attendanceProvider.notifier).loadTodayData(),
              child: const Text('Reessayer'),
            ),
          ] else if (state.todayLog?.checkIn != null)
            Text(
              'Arrivee : ${state.todayLog!.checkIn!.hour.toString().padLeft(2, '0')}:${state.todayLog!.checkIn!.minute.toString().padLeft(2, '0')}',
              style: const TextStyle(fontSize: 18),
            )
          else
            const Text(
              'Pret pour le pointage du jour.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey),
            ),
        ],
      ),
    );
  }

  Widget _buildSupervisorDashboard(
    BuildContext context,
    WidgetRef ref,
    AttendanceState state,
    Employee employee,
  ) {
    final overview = state.teamOverview;
    final items = overview?.items ?? const <TeamOverviewItem>[];
    final checkedInCount = items.where((item) => item.status != 'absent').length;
    final overtimeTotal = items.fold<double>(0, (sum, item) => sum + item.overtimeHours);
    final estimatedTotal = items.fold<double>(0, (sum, item) => sum + item.estimatedGain);
    final title = employee.isHrManager ? 'Pilotage RH' : 'Suivi manager';
    final currency = items.isNotEmpty ? items.first.currency : 'DA';

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          const Text(
            'Votre pointage personnel reste disponible au-dessus. Cette section ajoute la vue equipe de la phase 2.',
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
          if (state.teamLoading && items.isEmpty) ...[
            const Row(
              children: [
                SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Chargement du suivi d equipe...',
                    style: TextStyle(color: Colors.grey),
                  ),
                ),
              ],
            ),
          ] else if (state.teamError != null && items.isEmpty) ...[
            Text(
              state.teamError!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => ref.read(attendanceProvider.notifier).loadTeamOverview(),
              child: const Text('Reessayer le suivi equipe'),
            ),
          ] else ...[
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                _buildKpiChip('${items.length}', 'collaborateurs'),
                _buildKpiChip('$checkedInCount', 'pointes'),
                _buildKpiChip(overtimeTotal.toStringAsFixed(1), 'heures supp'),
                _buildKpiChip('${estimatedTotal.toStringAsFixed(0)} $currency', 'gagne estime'),
              ],
            ),
            const SizedBox(height: 16),
            if (items.isEmpty)
              const Text(
                'Aucune donnee equipe chargee pour le moment.',
                style: TextStyle(color: Colors.grey),
              )
            else ...[
              ...items.take(5).map((item) => _buildTeamMemberTile(context, item)),
              if (items.length > 5)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    '+ ${items.length - 5} autres collaborateurs',
                    style: const TextStyle(color: Colors.grey),
                  ),
                ),
            ],
            const SizedBox(height: 16),
            OutlinedButton(
              onPressed: () => ref.read(attendanceProvider.notifier).loadTeamOverview(),
              child: const Text('Actualiser le suivi'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildKpiChip(String value, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),
        ],
      ),
    );
  }

  Widget _buildTeamMemberTile(BuildContext context, TeamOverviewItem item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.03),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  item.name,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              Text(
                item.status,
                style: TextStyle(
                  color: item.status == 'absent' ? Colors.orangeAccent : Theme.of(context).primaryColor,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            'Role: ${item.managerRole ?? item.role ?? 'employe'}',
            style: const TextStyle(color: Colors.grey, fontSize: 12),
          ),
          const SizedBox(height: 4),
          Text(
            'Heures: ${item.hoursWorked.toStringAsFixed(2)} | Supp: ${item.overtimeHours.toStringAsFixed(2)} | Gain estime: ${item.estimatedGain.toStringAsFixed(2)} ${item.currency}',
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
          const SizedBox(height: 4),
          Text(
            'Entree: ${item.checkInTime ?? '--:--'} | Sortie: ${item.checkOutTime ?? '--:--'}',
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(BuildContext context, AttendanceState state) {
    if (state.summary == null) return const SizedBox.shrink();

    final currencyFormat = NumberFormat.currency(
      locale: 'fr_DZ',
      symbol: state.summary!.currency,
      decimalDigits: 2,
    );

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
              const Text('Gain estime aujourd\'hui', style: TextStyle(fontSize: 16)),
              const Spacer(),
              Text(
                currencyFormat.format(state.summary!.totalEstimated),
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: Theme.of(context).primaryColor,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'Heures sup : ${state.summary!.overtimeHours.toStringAsFixed(2)}h',
            style: const TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 8),
          const Text(
            'Estimation - net final calcule en fin de mois',
            style: TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic),
          ),
        ],
      ),
    );
  }

  Widget _buildNoticeCard(BuildContext context, String notice) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.amber.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.amber.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline, color: Colors.amber),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              notice,
              style: const TextStyle(color: Colors.white70),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref, AttendanceState state) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (state.isLoading && state.todayLog == null) ...[
          const Text(
            'L ecran est disponible pendant le chargement. Vous pouvez attendre quelques secondes ou tirer pour actualiser.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
        ],
        OutlinedButton(
          onPressed: () => context.push('/history'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Voir historique'),
        ),
        const SizedBox(height: 16),
        OutlinedButton(
          onPressed: () => context.push('/settings'),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
          child: const Text('Parametres'),
        ),
        const SizedBox(height: 16),
        TextButton(
          onPressed: () => ref.read(authProvider.notifier).logout(),
          child: const Text('Deconnexion', style: TextStyle(color: Colors.red)),
        ),
      ],
    );
  }
}
