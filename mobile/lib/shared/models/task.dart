// mobile/lib/shared/models/task.dart

enum TaskStatus { todo, inprogress, done, cancelled }
enum TaskPriority { low, normal, high, urgent }

TaskStatus taskStatusFromString(String s) {
  switch (s) {
    case 'inprogress': return TaskStatus.inprogress;
    case 'done': return TaskStatus.done;
    case 'cancelled': return TaskStatus.cancelled;
    default: return TaskStatus.todo;
  }
}

TaskPriority taskPriorityFromString(String s) {
  switch (s) {
    case 'low': return TaskPriority.low;
    case 'high': return TaskPriority.high;
    case 'urgent': return TaskPriority.urgent;
    default: return TaskPriority.normal;
  }
}

class ChecklistItem {
  final int id;
  final String label;
  final bool done;

  const ChecklistItem({required this.id, required this.label, required this.done});

  factory ChecklistItem.fromJson(Map<String, dynamic> json) => ChecklistItem(
    id: json['id'] as int,
    label: json['label'] as String,
    done: json['done'] as bool? ?? false,
  );

  Map<String, dynamic> toJson() => {'id': id, 'done': done};

  ChecklistItem copyWith({bool? done}) =>
      ChecklistItem(id: id, label: label, done: done ?? this.done);
}

class TaskProject {
  final int id;
  final String name;
  const TaskProject({required this.id, required this.name});
  factory TaskProject.fromJson(Map<String, dynamic> json) =>
      TaskProject(id: json['id'] as int, name: json['name'] as String);
}

class Task {
  final int id;
  final String title;
  final String? description;
  final TaskStatus status;
  final TaskPriority priority;
  final TaskProject? project;
  final DateTime? dueDate;   // TOUJOURS DateTime
  final List<ChecklistItem> checklist;
  final int commentsCount;
  final DateTime? createdAt;

  const Task({
    required this.id,
    required this.title,
    this.description,
    required this.status,
    required this.priority,
    this.project,
    this.dueDate,
    this.checklist = const [],
    this.commentsCount = 0,
    this.createdAt,
  });

  factory Task.fromJson(Map<String, dynamic> json) => Task(
    id: json['id'] as int,
    title: json['title'] as String,
    description: json['description'] as String?,
    status: taskStatusFromString(json['status'] as String),
    priority: taskPriorityFromString(json['priority'] as String),
    project: json['project'] != null
        ? TaskProject.fromJson(json['project'] as Map<String, dynamic>)
        : null,
    dueDate: json['due_date'] != null
        ? DateTime.parse(json['due_date'] as String)
        : null,
    checklist: json['checklist'] != null
        ? (json['checklist'] as List)
            .map((e) => ChecklistItem.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    commentsCount: json['comments_count'] as int? ?? 0,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );

  bool get isDone => status == TaskStatus.done;
  bool get isOverdue => dueDate != null && dueDate!.isBefore(DateTime.now()) && !isDone;
  int get checklistCompleted => checklist.where((i) => i.done).length;
  double get checklistProgress =>
      checklist.isEmpty ? 0.0 : checklistCompleted / checklist.length;

  Task copyWith({TaskStatus? status, List<ChecklistItem>? checklist}) => Task(
    id: id,
    title: title,
    description: description,
    status: status ?? this.status,
    priority: priority,
    project: project,
    dueDate: dueDate,
    checklist: checklist ?? this.checklist,
    commentsCount: commentsCount,
    createdAt: createdAt,
  );
}

class TaskComment {
  final int id;
  final String authorName;
  final String? authorPhotoUrl;
  final String content;
  final DateTime createdAt;  // TOUJOURS DateTime

  const TaskComment({
    required this.id,
    required this.authorName,
    this.authorPhotoUrl,
    required this.content,
    required this.createdAt,
  });

  factory TaskComment.fromJson(Map<String, dynamic> json) {
    final author = json['author'] as Map<String, dynamic>;
    return TaskComment(
      id: json['id'] as int,
      authorName: author['name'] as String,
      authorPhotoUrl: author['photo_url'] as String?,
      content: json['content'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
