<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first active course or create a test course
        $course = Course::active()->first();
        
        if (!$course) {
            $course = Course::create([
                'name' => 'Test Course',
                'slug' => 'test-course',
                'description' => 'Test course for quiz development',
                'namespace' => 'test-course',
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        // Create a sample quiz
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'General Knowledge Quiz',
            'description' => 'A comprehensive quiz covering various topics',
            'type' => 'repository',
            'duration_minutes' => 30,
            'total_questions' => 30,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'show_correct_answers' => true,
            'feedback_timing' => 'after_submission',
            'is_active' => true,
        ]);

        // Sample questions with different topics and difficulties
        $questions = [
            // Mathematics - Easy
            [
                'question' => 'What is 5 + 7?',
                'topic' => 'Mathematics',
                'difficulty' => 'easy',
                'explanation' => 'Simple addition: 5 + 7 = 12',
                'options' => [
                    ['A', '10', false],
                    ['B', '12', true],
                    ['C', '14', false],
                    ['D', '15', false],
                ],
            ],
            [
                'question' => 'What is 10 × 3?',
                'topic' => 'Mathematics',
                'difficulty' => 'easy',
                'explanation' => 'Multiplication: 10 × 3 = 30',
                'options' => [
                    ['A', '20', false],
                    ['B', '25', false],
                    ['C', '30', true],
                    ['D', '35', false],
                ],
            ],
            
            // Mathematics - Medium
            [
                'question' => 'What is the square root of 144?',
                'topic' => 'Mathematics',
                'difficulty' => 'medium',
                'explanation' => '12 × 12 = 144, so √144 = 12',
                'options' => [
                    ['A', '10', false],
                    ['B', '11', false],
                    ['C', '12', true],
                    ['D', '13', false],
                ],
            ],
            [
                'question' => 'If x + 5 = 12, what is x?',
                'topic' => 'Mathematics',
                'difficulty' => 'medium',
                'explanation' => 'Subtract 5 from both sides: x = 12 - 5 = 7',
                'options' => [
                    ['A', '5', false],
                    ['B', '6', false],
                    ['C', '7', true],
                    ['D', '8', false],
                ],
            ],
            
            // Mathematics - Hard
            [
                'question' => 'What is the derivative of x² + 3x?',
                'topic' => 'Mathematics',
                'difficulty' => 'hard',
                'explanation' => 'Using power rule: d/dx(x²) = 2x, d/dx(3x) = 3, so the answer is 2x + 3',
                'options' => [
                    ['A', 'x + 3', false],
                    ['B', '2x + 3', true],
                    ['C', 'x² + 3', false],
                    ['D', '2x²', false],
                ],
            ],
            
            // Science - Easy
            [
                'question' => 'What is the chemical symbol for water?',
                'topic' => 'Science',
                'difficulty' => 'easy',
                'explanation' => 'Water is composed of 2 hydrogen atoms and 1 oxygen atom: H₂O',
                'options' => [
                    ['A', 'O₂', false],
                    ['B', 'H₂O', true],
                    ['C', 'CO₂', false],
                    ['D', 'NaCl', false],
                ],
            ],
            [
                'question' => 'How many planets are in our solar system?',
                'topic' => 'Science',
                'difficulty' => 'easy',
                'explanation' => 'The 8 planets are: Mercury, Venus, Earth, Mars, Jupiter, Saturn, Uranus, Neptune',
                'options' => [
                    ['A', '7', false],
                    ['B', '8', true],
                    ['C', '9', false],
                    ['D', '10', false],
                ],
            ],
            
            // Science - Medium
            [
                'question' => 'What is the speed of light in vacuum?',
                'topic' => 'Science',
                'difficulty' => 'medium',
                'explanation' => 'The speed of light in vacuum is approximately 299,792,458 m/s or about 300,000 km/s',
                'options' => [
                    ['A', '150,000 km/s', false],
                    ['B', '200,000 km/s', false],
                    ['C', '300,000 km/s', true],
                    ['D', '400,000 km/s', false],
                ],
            ],
            [
                'question' => 'What is the powerhouse of the cell?',
                'topic' => 'Science',
                'difficulty' => 'medium',
                'explanation' => 'Mitochondria generate most of the cell\'s ATP through cellular respiration',
                'options' => [
                    ['A', 'Nucleus', false],
                    ['B', 'Mitochondria', true],
                    ['C', 'Ribosome', false],
                    ['D', 'Chloroplast', false],
                ],
            ],
            
            // Science - Hard
            [
                'question' => 'What is Avogadro\'s number?',
                'topic' => 'Science',
                'difficulty' => 'hard',
                'explanation' => 'Avogadro\'s number is 6.022 × 10²³, representing the number of particles in one mole',
                'options' => [
                    ['A', '3.14 × 10²³', false],
                    ['B', '6.022 × 10²³', true],
                    ['C', '9.81 × 10²³', false],
                    ['D', '1.602 × 10²³', false],
                ],
            ],
            
            // History - Easy
            [
                'question' => 'Who was the first President of the United States?',
                'topic' => 'History',
                'difficulty' => 'easy',
                'explanation' => 'George Washington served as the first U.S. President from 1789 to 1797',
                'options' => [
                    ['A', 'Thomas Jefferson', false],
                    ['B', 'George Washington', true],
                    ['C', 'Abraham Lincoln', false],
                    ['D', 'Benjamin Franklin', false],
                ],
            ],
            [
                'question' => 'In which year did World War II end?',
                'topic' => 'History',
                'difficulty' => 'easy',
                'explanation' => 'World War II ended in 1945 with the surrender of Japan in September',
                'options' => [
                    ['A', '1943', false],
                    ['B', '1944', false],
                    ['C', '1945', true],
                    ['D', '1946', false],
                ],
            ],
            
            // History - Medium
            [
                'question' => 'Who wrote the Declaration of Independence?',
                'topic' => 'History',
                'difficulty' => 'medium',
                'explanation' => 'Thomas Jefferson was the primary author of the Declaration of Independence in 1776',
                'options' => [
                    ['A', 'George Washington', false],
                    ['B', 'Benjamin Franklin', false],
                    ['C', 'Thomas Jefferson', true],
                    ['D', 'John Adams', false],
                ],
            ],
            [
                'question' => 'What year did the Berlin Wall fall?',
                'topic' => 'History',
                'difficulty' => 'medium',
                'explanation' => 'The Berlin Wall fell on November 9, 1989, marking the end of the Cold War era',
                'options' => [
                    ['A', '1987', false],
                    ['B', '1988', false],
                    ['C', '1989', true],
                    ['D', '1990', false],
                ],
            ],
            
            // History - Hard
            [
                'question' => 'What was the name of the first permanent English settlement in America?',
                'topic' => 'History',
                'difficulty' => 'hard',
                'explanation' => 'Jamestown, Virginia, was established in 1607 as the first permanent English settlement',
                'options' => [
                    ['A', 'Plymouth', false],
                    ['B', 'Jamestown', true],
                    ['C', 'Roanoke', false],
                    ['D', 'Boston', false],
                ],
            ],
            
            // Geography - Easy
            [
                'question' => 'What is the capital of France?',
                'topic' => 'Geography',
                'difficulty' => 'easy',
                'explanation' => 'Paris is the capital and largest city of France',
                'options' => [
                    ['A', 'London', false],
                    ['B', 'Berlin', false],
                    ['C', 'Paris', true],
                    ['D', 'Rome', false],
                ],
            ],
            [
                'question' => 'Which continent is the largest by area?',
                'topic' => 'Geography',
                'difficulty' => 'easy',
                'explanation' => 'Asia is the largest continent, covering about 30% of Earth\'s land area',
                'options' => [
                    ['A', 'Africa', false],
                    ['B', 'Asia', true],
                    ['C', 'Europe', false],
                    ['D', 'North America', false],
                ],
            ],
            
            // Geography - Medium
            [
                'question' => 'What is the longest river in the world?',
                'topic' => 'Geography',
                'difficulty' => 'medium',
                'explanation' => 'The Nile River in Africa is approximately 6,650 km long',
                'options' => [
                    ['A', 'Amazon', false],
                    ['B', 'Nile', true],
                    ['C', 'Yangtze', false],
                    ['D', 'Mississippi', false],
                ],
            ],
            [
                'question' => 'Which country has the most time zones?',
                'topic' => 'Geography',
                'difficulty' => 'medium',
                'explanation' => 'France has 12 time zones due to its overseas territories',
                'options' => [
                    ['A', 'Russia', false],
                    ['B', 'United States', false],
                    ['C', 'France', true],
                    ['D', 'China', false],
                ],
            ],
            
            // Geography - Hard
            [
                'question' => 'What is the smallest country in the world by area?',
                'topic' => 'Geography',
                'difficulty' => 'hard',
                'explanation' => 'Vatican City is the smallest country, with an area of about 0.44 km²',
                'options' => [
                    ['A', 'Monaco', false],
                    ['B', 'Vatican City', true],
                    ['C', 'San Marino', false],
                    ['D', 'Liechtenstein', false],
                ],
            ],
            
            // Literature - Easy
            [
                'question' => 'Who wrote "Romeo and Juliet"?',
                'topic' => 'Literature',
                'difficulty' => 'easy',
                'explanation' => 'William Shakespeare wrote Romeo and Juliet around 1594-1596',
                'options' => [
                    ['A', 'Charles Dickens', false],
                    ['B', 'William Shakespeare', true],
                    ['C', 'Jane Austen', false],
                    ['D', 'Mark Twain', false],
                ],
            ],
            [
                'question' => 'What is the first book of the Harry Potter series?',
                'topic' => 'Literature',
                'difficulty' => 'easy',
                'explanation' => 'Harry Potter and the Philosopher\'s Stone (or Sorcerer\'s Stone in the US) was published in 1997',
                'options' => [
                    ['A', 'Chamber of Secrets', false],
                    ['B', 'Philosopher\'s Stone', true],
                    ['C', 'Prisoner of Azkaban', false],
                    ['D', 'Goblet of Fire', false],
                ],
            ],
            
            // Literature - Medium
            [
                'question' => 'Who wrote "1984"?',
                'topic' => 'Literature',
                'difficulty' => 'medium',
                'explanation' => 'George Orwell wrote the dystopian novel "1984", published in 1949',
                'options' => [
                    ['A', 'Aldous Huxley', false],
                    ['B', 'George Orwell', true],
                    ['C', 'Ray Bradbury', false],
                    ['D', 'H.G. Wells', false],
                ],
            ],
            [
                'question' => 'In which novel does the character Atticus Finch appear?',
                'topic' => 'Literature',
                'difficulty' => 'medium',
                'explanation' => 'Atticus Finch is the protagonist in Harper Lee\'s "To Kill a Mockingbird"',
                'options' => [
                    ['A', 'The Great Gatsby', false],
                    ['B', 'To Kill a Mockingbird', true],
                    ['C', 'Of Mice and Men', false],
                    ['D', 'The Catcher in the Rye', false],
                ],
            ],
            
            // Literature - Hard
            [
                'question' => 'Who wrote "One Hundred Years of Solitude"?',
                'topic' => 'Literature',
                'difficulty' => 'hard',
                'explanation' => 'Gabriel García Márquez wrote this masterpiece of magical realism in 1967',
                'options' => [
                    ['A', 'Jorge Luis Borges', false],
                    ['B', 'Gabriel García Márquez', true],
                    ['C', 'Pablo Neruda', false],
                    ['D', 'Mario Vargas Llosa', false],
                ],
            ],
            
            // Additional questions to reach 30
            [
                'question' => 'What is the capital of Japan?',
                'topic' => 'Geography',
                'difficulty' => 'easy',
                'explanation' => 'Tokyo is the capital and most populous city of Japan',
                'options' => [
                    ['A', 'Osaka', false],
                    ['B', 'Kyoto', false],
                    ['C', 'Tokyo', true],
                    ['D', 'Hiroshima', false],
                ],
            ],
            [
                'question' => 'What is 15% of 200?',
                'topic' => 'Mathematics',
                'difficulty' => 'easy',
                'explanation' => '15% of 200 = 0.15 × 200 = 30',
                'options' => [
                    ['A', '25', false],
                    ['B', '30', true],
                    ['C', '35', false],
                    ['D', '40', false],
                ],
            ],
            [
                'question' => 'What gas do plants absorb from the atmosphere?',
                'topic' => 'Science',
                'difficulty' => 'easy',
                'explanation' => 'Plants absorb carbon dioxide (CO₂) during photosynthesis',
                'options' => [
                    ['A', 'Oxygen', false],
                    ['B', 'Carbon Dioxide', true],
                    ['C', 'Nitrogen', false],
                    ['D', 'Hydrogen', false],
                ],
            ],
            [
                'question' => 'Who painted the Mona Lisa?',
                'topic' => 'Art',
                'difficulty' => 'easy',
                'explanation' => 'Leonardo da Vinci painted the Mona Lisa in the early 16th century',
                'options' => [
                    ['A', 'Michelangelo', false],
                    ['B', 'Leonardo da Vinci', true],
                    ['C', 'Raphael', false],
                    ['D', 'Donatello', false],
                ],
            ],
            [
                'question' => 'What is the largest ocean on Earth?',
                'topic' => 'Geography',
                'difficulty' => 'easy',
                'explanation' => 'The Pacific Ocean covers about 46% of Earth\'s water surface',
                'options' => [
                    ['A', 'Atlantic', false],
                    ['B', 'Indian', false],
                    ['C', 'Pacific', true],
                    ['D', 'Arctic', false],
                ],
            ],
        ];

        // Create questions and their options
        foreach ($questions as $questionData) {
            $question = QuizQuestion::create([
                'course_id' => $course->id,
                'question_text' => $questionData['question'],
                'explanation' => $questionData['explanation'],
                'difficulty' => $questionData['difficulty'],
                'topic' => $questionData['topic'],
                'type' => 'repository',
                'is_active' => true,
            ]);

            foreach ($questionData['options'] as $optionData) {
                QuizQuestionOption::create([
                    'quiz_question_id' => $question->id,
                    'option_letter' => $optionData[0],
                    'option_text' => $optionData[1],
                    'is_correct' => $optionData[2],
                ]);
            }
        }

        $this->command->info('Quiz seeder completed! Created ' . count($questions) . ' questions.');
    }
}
