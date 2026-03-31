// mobile/lib/shared/models/project.dart

enum ProjectStatus { active, paused, completed, cancelled }

class Project {
  final int id;
  final String name;
  final String? description;
  final ProjectStatus status;
  final int tasksCount;
  final int tasksDone;
  final DateTime? createdAt;

  const Project({
    required this.id,
    required this.name,
    this.description,
    required this.status,
    this.tasksCount = 0,
    this.tasksDone = 0,
    this.createdAt,
  });

  factory Project.fromJson(Map<String, dynamic> json) => Project(
    id: json['id'] as int,
    name: json['name'] as String,
    description: json['description'] as String?,
    status: _statusFromString(json['status'] as String),
    tasksCount: json['tasks_count'] as int? ?? 0,
    tasksDone: json['tasks_done'] as int? ?? 0,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  static ProjectStatus _statusFromString(String s) {
    switch (s) {
      case 'paused': return ProjectStatus.paused;
      case 'completed': return ProjectStatus.completed;
      case 'cancelled': return ProjectStatus.cancelled;
      default: return ProjectStatus.active;
    }
  }

  double get completionRate => tasksCount > 0 ? tasksDone / tasksCount : 0.0;
}
